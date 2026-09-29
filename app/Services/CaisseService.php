<?php

namespace App\Services;

use App\Models\Abonnement;
use App\Models\Caisse;
use App\Models\Client;
use App\Models\Cloture;
use App\Models\Coach;
use App\Models\CodePromo;
use App\Models\Formule;
use App\Models\MouvementStock;
use App\Models\PackCoaching;
use App\Models\Paiement;
use App\Models\Produit;
use App\Models\User;
use App\Support\Fcfa;
use App\Support\Journal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CaisseService
{
    public function __construct(
        private PointageService $pointage,
        private MessageService $messages,
    ) {}

    /**
     * Encaisse une entrée journalière, enregistre le passage et numérote le reçu.
     *
     * @param  array{client_id?: ?int, nom?: ?string, telephone?: ?string, montant: int, mode: string, reference?: ?string}  $data
     */
    public function encaisserJournalier(array $data, User $caissier): Paiement
    {
        $caisse = $this->caisseOuverte($caissier);

        $paiement = DB::transaction(function () use ($data, $caissier, $caisse) {
            $client = $this->resoudreClientJournalier($data);

            $paiement = $this->creerPaiement([
                'client_id' => $client?->id,
                'type' => Paiement::TYPE_JOURNALIER,
                'montant' => $data['montant'],
            ], $data, $caissier, $caisse);

            $this->pointage->parCaisse($paiement, $caissier);

            return $paiement;
        });

        $this->messages->recu($paiement);

        return $paiement;
    }

    /**
     * Souscription, renouvellement ou carnet d'entrées.
     * Un abonnement à la durée démarre le lendemain de la fin des droits en cours (aucun jour perdu).
     */
    public function souscrireAbonnement(Client $client, Formule $formule, array $data, ?User $caissier, ?Caisse $caisse = null): Paiement
    {
        $caisse ??= $this->caisseOuverte($caissier);
        $codePromo = $this->codePromo($data['code_promo'] ?? null);

        $paiement = DB::transaction(function () use ($client, $formule, $data, $caissier, $caisse, $codePromo) {
            $premier = ! $client->abonnements()->where('statut', Abonnement::STATUT_ACTIF)->exists();
            $frais = $premier && ! empty($data['frais_inscription']) ? (int) config('salle.frais_inscription') : 0;
            $remise = $codePromo?->remisePour($formule->prix) ?? 0;

            if ($formule->estCarnet()) {
                $debut = today();
            } else {
                $finActuelle = $client->finDesDroits();
                $debut = $finActuelle ? $finActuelle->copy()->addDay() : today();
            }

            $abonnement = Abonnement::create([
                'client_id' => $client->id,
                'formule_id' => $formule->id,
                'date_debut' => $debut,
                'date_fin' => $debut->copy()->addDays($formule->duree_jours - 1),
                'montant' => $formule->prix - $remise,
                'entrees_restantes' => $formule->estCarnet() ? $formule->nb_entrees : null,
                'remise' => $remise,
                'frais_inscription' => $frais,
                'code_promo_id' => $codePromo?->id,
                'statut' => Abonnement::STATUT_ACTIF,
                'est_renouvellement' => ! $premier,
                'user_id' => $caissier?->id,
            ]);

            $codePromo?->increment('utilisations');

            // Un journalier qui s'abonne devient abonné
            if ($client->type !== Client::TYPE_ABONNE) {
                $client->update(['type' => Client::TYPE_ABONNE]);
            }

            return $this->creerPaiement([
                'client_id' => $client->id,
                'abonnement_id' => $abonnement->id,
                'type' => Paiement::TYPE_ABONNEMENT,
                'montant' => $formule->prix - $remise + $frais,
            ], $data, $caissier, $caisse);
        });

        $this->messages->recu($paiement);

        return $paiement;
    }

    /** Vente d'un pack de séances de coaching personnel. */
    public function vendreCoaching(Client $client, Formule $formule, ?Coach $coach, array $data, User $caissier): Paiement
    {
        $caisse = $this->caisseOuverte($caissier);

        return DB::transaction(function () use ($client, $formule, $coach, $data, $caissier, $caisse) {
            $paiement = $this->creerPaiement([
                'client_id' => $client->id,
                'type' => Paiement::TYPE_COACHING,
                'montant' => $formule->prix,
            ], $data, $caissier, $caisse);

            PackCoaching::create([
                'client_id' => $client->id,
                'coach_id' => $coach?->id,
                'formule_id' => $formule->id,
                'paiement_id' => $paiement->id,
                'seances_total' => $formule->nb_seances,
                'seances_restantes' => $formule->nb_seances,
                'montant' => $formule->prix,
                'expire_le' => today()->addDays($formule->duree_jours - 1),
            ]);

            return $paiement;
        });
    }

    /**
     * Vente au comptoir (boissons, compléments…) avec sortie de stock.
     *
     * @param  array<int, int>  $quantites  produit_id => quantité
     */
    public function vendreProduits(array $quantites, array $data, User $caissier, ?Client $client = null): Paiement
    {
        $caisse = $this->caisseOuverte($caissier);
        $quantites = array_filter(array_map('intval', $quantites), fn ($q) => $q > 0);

        if (! $quantites) {
            throw ValidationException::withMessages(['produits' => 'Ajoutez au moins un article.']);
        }

        return DB::transaction(function () use ($quantites, $data, $caissier, $caisse, $client) {
            $produits = Produit::whereIn('id', array_keys($quantites))->where('actif', true)->lockForUpdate()->get()->keyBy('id');

            foreach ($quantites as $id => $qte) {
                $produit = $produits->get($id) ?? throw ValidationException::withMessages(['produits' => 'Un article n’est plus disponible.']);
                if ($produit->stock < $qte) {
                    throw ValidationException::withMessages(['produits' => "Stock insuffisant pour {$produit->nom} : il en reste {$produit->stock}."]);
                }
            }

            $total = collect($quantites)->sum(fn ($qte, $id) => $produits[$id]->prix * $qte);
            $paiement = $this->creerPaiement(['client_id' => $client?->id, 'type' => Paiement::TYPE_VENTE, 'montant' => $total], $data, $caissier, $caisse);

            foreach ($quantites as $id => $qte) {
                $produit = $produits[$id];
                $paiement->lignes()->create(['produit_id' => $id, 'libelle' => $produit->nom, 'quantite' => $qte, 'prix_unitaire' => $produit->prix]);
                $produit->decrement('stock', $qte);
                MouvementStock::create(['produit_id' => $id, 'quantite' => -$qte, 'motif' => 'vente', 'paiement_id' => $paiement->id, 'user_id' => $caissier->id]);
            }

            return $paiement;
        });
    }

    /** Annule un reçu : il reste visible, mais ne compte plus dans les recettes. */
    public function annuler(Paiement $paiement, string $motif, User $auteur): void
    {
        if ($paiement->estAnnule()) {
            throw ValidationException::withMessages(['motif' => 'Ce reçu est déjà annulé.']);
        }

        if (! $auteur->isAdmin()) {
            $memeCaisse = $paiement->caisse_id === $auteur->caisse_id;
            $aujourdhui = $paiement->created_at->isToday();
            if (! $memeCaisse || ! $aujourdhui || $paiement->caisse?->estClotureeLe()) {
                throw ValidationException::withMessages(['motif' => "Seul l'administrateur peut annuler ce reçu (autre caisse, autre jour ou caisse déjà clôturée)."]);
            }
        }

        DB::transaction(function () use ($paiement, $motif, $auteur) {
            $paiement->update(['annule_le' => now(), 'annule_par' => $auteur->id, 'motif_annulation' => $motif]);

            $paiement->abonnement?->update(['statut' => Abonnement::STATUT_ANNULE]);
            $paiement->pack?->update(['statut' => 'annule', 'seances_restantes' => 0]);

            foreach ($paiement->lignes as $ligne) {
                if ($ligne->produit_id) {
                    Produit::whereKey($ligne->produit_id)->increment('stock', $ligne->quantite);
                    MouvementStock::create(['produit_id' => $ligne->produit_id, 'quantite' => $ligne->quantite, 'motif' => 'annulation', 'paiement_id' => $paiement->id, 'user_id' => $auteur->id]);
                }
            }

            Journal::noter('paiement.annule', "Reçu {$paiement->numero_recu} annulé ({$motif})", $paiement, [
                'montant' => $paiement->montant, 'type' => $paiement->type, 'motif' => $motif,
            ]);
        });
    }

    /**
     * Clôture de la journée : compare les espèces comptées à ce que le tiroir devrait contenir.
     *
     * @param  array<int, int>  $coupures  valeur du billet ou de la pièce => nombre
     */
    public function cloturer(Caisse $caisse, User $auteur, array $coupures, int $fond, ?string $motif): Cloture
    {
        if ($caisse->estClotureeLe()) {
            throw ValidationException::withMessages(['coupures' => 'Cette caisse est déjà clôturée pour aujourd’hui.']);
        }

        $coupures = collect($coupures)->only(Cloture::COUPURES)->map(fn ($n) => max(0, (int) $n))->filter();
        $comptees = $coupures->sum(fn ($n, $valeur) => $n * $valeur);

        $paiements = Paiement::valides()->where('caisse_id', $caisse->id)->whereDate('created_at', today()->toDateString())->get();
        $especes = $paiements->whereIn('mode', Paiement::MODES_ESPECES)->sum('montant');
        $ecart = $comptees - ($fond + $especes);

        if ($ecart !== 0 && ! trim((string) $motif)) {
            throw ValidationException::withMessages(['motif_ecart' => 'Il y a un écart de '.Fcfa::format($ecart).' : recomptez ou indiquez la raison.']);
        }

        $cloture = Cloture::create([
            'caisse_id' => $caisse->id,
            'user_id' => $auteur->id,
            'jour' => today(),
            'fond_caisse' => $fond,
            'especes_encaissees' => $especes,
            'especes_comptees' => $comptees,
            'ecart' => $ecart,
            'electronique' => $paiements->sum('montant') - $especes,
            'coupures' => $coupures->all(),
            'motif_ecart' => $motif ?: null,
        ]);

        Journal::noter('caisse.cloturee', "{$caisse->nom} clôturée · écart ".Fcfa::format($ecart), $cloture);

        return $cloture;
    }

    /** Caisse de la caissière, active et pas encore clôturée aujourd'hui. */
    public function caisseOuverte(?User $caissier): Caisse
    {
        $caisse = $caissier?->caisseActive();

        if (! $caisse) {
            throw ValidationException::withMessages(['caisse' => "Aucune caisse active ne vous est attribuée."]);
        }
        if ($caisse->estClotureeLe()) {
            throw ValidationException::withMessages(['caisse' => "La caisse « {$caisse->nom} » est clôturée pour aujourd'hui : plus d'encaissement possible."]);
        }

        return $caisse;
    }

    private function codePromo(?string $code): ?CodePromo
    {
        if (! $code = trim((string) $code)) {
            return null;
        }

        $promo = CodePromo::whereRaw('UPPER(code) = ?', [mb_strtoupper($code)])->first();
        if (! $promo?->estUtilisable()) {
            throw ValidationException::withMessages(['code_promo' => "Le code promo « {$code} » n'existe pas ou n'est plus valable."]);
        }

        return $promo;
    }

    private function creerPaiement(array $attributs, array $data, ?User $caissier, ?Caisse $caisse): Paiement
    {
        $paiement = Paiement::create($attributs + [
            'user_id' => $caissier?->id,
            'caisse_id' => $caisse?->id,
            'mode' => $data['mode'],
            'reference' => $data['reference'] ?? null,
        ]);

        $paiement->update(['numero_recu' => sprintf('R%s-%06d', now()->format('Ymd'), $paiement->id)]);

        return $paiement;
    }

    /** Client identifié si possible ; sinon passage anonyme (client null). */
    private function resoudreClientJournalier(array $data): ?Client
    {
        if (! empty($data['client_id'])) {
            return Client::findOrFail($data['client_id']);
        }

        if (! empty($data['telephone'])) {
            return Client::firstOrCreate(
                ['telephone' => $data['telephone']],
                ['type' => Client::TYPE_JOURNALIER, 'nom' => ($data['nom'] ?? null) ?: 'Client journalier']
            );
        }

        if (! empty($data['nom'])) {
            return Client::create(['type' => Client::TYPE_JOURNALIER, 'nom' => $data['nom']]);
        }

        return null;
    }
}
