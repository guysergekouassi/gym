<?php

namespace App\Services;

use App\Models\Abonnement;
use App\Models\Client;
use App\Models\Formule;
use App\Models\Paiement;
use App\Models\Parametre;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CaisseService
{
    public function __construct(
        private PointageService $pointage,
        private SynchroPointeuseService $pointeuse,
    ) {}

    /**
     * Encaisse une entrée journalière, enregistre le passage et numérote le reçu.
     * Le montant est toujours le tarif fixé par l'admin : la caissière ne le saisit pas.
     *
     * @param  array{client_id?: ?int, nom?: ?string, telephone?: ?string, quantite?: ?int, mode: string, reference?: ?string}  $data
     */
    public function encaisserJournalier(array $data, User $caissier): Paiement
    {
        $paiement = DB::transaction(function () use ($data, $caissier) {
            $client = $this->resoudreClientJournalier($data);
            $quantite = max(1, min(10, (int) ($data['quantite'] ?? 1)));

            $paiement = Paiement::create([
                'client_id' => $client?->id,
                'user_id' => $caissier->id,
                'type' => Paiement::TYPE_JOURNALIER,
                'montant' => Parametre::tarifJournalier() * $quantite,
                'quantite' => $quantite,
                'mode' => $data['mode'],
                'reference' => $data['reference'] ?? null,
            ]);

            $this->numeroter($paiement);
            // Une entrée par personne ; seule la première est rattachée au client identifié
            for ($i = 0; $i < $quantite; $i++) {
                $this->pointage->parCaisse($paiement, $caissier, $i === 0);
            }

            return $paiement;
        });

        // Journalier qui a son empreinte : la pointeuse le laisse entrer aujourd'hui
        if ($paiement->client) {
            $this->pointeuse->ajouterOuModifier($paiement->client);
        }

        return $paiement;
    }

    /**
     * Souscription ou renouvellement. Si un abonnement est en cours,
     * le nouveau démarre le lendemain de sa fin (aucun jour perdu).
     */
    public function souscrireAbonnement(Client $client, Formule $formule, array $data, User $caissier): Paiement
    {
        $paiement = DB::transaction(function () use ($client, $formule, $data, $caissier) {
            $finActuelle = $client->finDesDroits();
            $debut = $finActuelle ? $finActuelle->copy()->addDay() : today();

            $abonnement = Abonnement::create([
                'client_id' => $client->id,
                'formule_id' => $formule->id,
                'date_debut' => $debut,
                'date_fin' => $debut->copy()->addDays($formule->duree_jours - 1),
                'montant' => $formule->prix,
                'statut' => Abonnement::STATUT_ACTIF,
                'est_renouvellement' => $client->abonnements()->exists(),
                'user_id' => $caissier->id,
            ]);

            // Un journalier qui s'abonne devient abonné
            if ($client->type !== Client::TYPE_ABONNE) {
                $client->update(['type' => Client::TYPE_ABONNE]);
            }

            $paiement = Paiement::create([
                'client_id' => $client->id,
                'abonnement_id' => $abonnement->id,
                'user_id' => $caissier->id,
                'type' => Paiement::TYPE_ABONNEMENT,
                'montant' => $formule->prix,
                'mode' => $data['mode'],
                'reference' => $data['reference'] ?? null,
            ]);

            $this->numeroter($paiement);

            return $paiement;
        });

        // La pointeuse reçoit la nouvelle date de fin des droits
        $this->pointeuse->ajouterOuModifier($client->refresh());

        return $paiement;
    }

    /**
     * Annule un encaissement (erreur de caisse). Rien n'est supprimé : le paiement
     * reste visible, barré, avec l'auteur, la date et le motif de l'annulation.
     */
    public function annuler(Paiement $paiement, User $admin, string $motif): void
    {
        DB::transaction(function () use ($paiement, $admin, $motif) {
            $paiement = Paiement::lockForUpdate()->findOrFail($paiement->id);

            if ($paiement->estAnnule()) {
                throw new RuntimeException('Ce paiement est déjà annulé.');
            }

            $paiement->forceFill([
                'annule_le' => now(),
                'annule_par' => $admin->id,
                'motif_annulation' => $motif,
            ])->save();

            $paiement->abonnement?->update(['statut' => Abonnement::STATUT_ANNULE]);
        });

        if ($paiement->client) {
            $this->pointeuse->ajouterOuModifier($paiement->client); // droits recalculés sans ce paiement
        }

        Log::notice('Paiement annulé', [
            'recu' => $paiement->numero_recu,
            'montant' => $paiement->montant,
            'par' => $admin->id,
        ]);
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

    private function numeroter(Paiement $paiement): void
    {
        $paiement->update([
            'numero_recu' => sprintf('R%s-%06d', now()->format('Ymd'), $paiement->id),
        ]);
    }
}
