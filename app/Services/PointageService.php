<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Lecteur;
use App\Models\Paiement;
use App\Models\Passage;
use App\Models\User;
use App\Support\Horaires;
use Carbon\CarbonInterface;

class PointageService
{
    public function __construct(private SynchroPointeuseService $synchro) {}

    /**
     * Passage à la pointeuse : doigt (ou carte) reconnu par l'appareil, qui transmet
     * le n° d'utilisateur. Sources : la pointeuse en réseau (protocole Cloud/ADMS),
     * l'API avec token, ou la saisie du n° au clavier sur l'écran d'accueil (secours).
     *
     * $quand = heure réelle du passage (la pointeuse peut renvoyer des pointages
     * enregistrés pendant une coupure réseau).
     */
    public function parEmpreinte(string $empreinteId, ?Lecteur $lecteur = null, ?User $poste = null, ?CarbonInterface $quand = null): Passage
    {
        // Pointage horodaté déjà reçu (la pointeuse renvoie parfois le même lot) : on ne le double pas
        if ($lecteur && $quand) {
            $existant = Passage::where('lecteur_id', $lecteur->id)
                ->where('empreinte_id', $empreinteId)
                ->where('passe_le', $quand)
                ->first();

            if ($existant) {
                return $existant;
            }
        }

        $quand ??= now();

        $client = Client::where('empreinte_id', $empreinteId)->first();

        if (! $client) {
            return $this->enregistrer([
                'lecteur_id' => $lecteur?->id,
                'user_id' => $poste?->id,
                'methode' => Passage::METHODE_EMPREINTE,
                'statut' => Passage::STATUT_REFUSE,
                'motif' => 'empreinte_inconnue',
                'empreinte_id' => $empreinteId,
                'passe_le' => $quand,
            ]);
        }

        // Anti-doublon : un client qui repose le doigt plusieurs fois ne crée qu'un passage
        $recent = Passage::where('client_id', $client->id)
            ->where('statut', Passage::STATUT_AUTORISE)
            ->whereBetween('passe_le', [$quand->copy()->subSeconds((int) config('salle.anti_doublon_secondes')), $quand])
            ->latest('passe_le')
            ->first();

        if ($recent) {
            return $recent;
        }

        [$autorise, $motif, $carnet] = $this->verifierDroit($client, $quand);
        $sens = $autorise ? $this->sens($client, $quand) : null;

        $passage = $this->enregistrer([
            'client_id' => $client->id,
            'lecteur_id' => $lecteur?->id,
            'user_id' => $poste?->id,
            // L'arrivée décompte une séance du carnet Fidélité
            'paiement_id' => $sens === Passage::SENS_ENTREE ? $carnet?->id : null,
            'methode' => Passage::METHODE_EMPREINTE,
            'statut' => $autorise ? Passage::STATUT_AUTORISE : Passage::STATUT_REFUSE,
            'sens' => $sens,
            'motif' => $motif,
            'empreinte_id' => $empreinteId,
            'passe_le' => $quand,
        ]);

        // La pointeuse suit : séance de carnet décomptée, ou passe 1 séance/jour terminée (accès fermé jusqu'à demain)
        if ($passage->paiement_id || ($sens === Passage::SENS_DEPART && $client->seanceDuJourFaite($quand))) {
            $this->synchro->ajouterOuModifier($client);
        }

        return $passage;
    }

    /** Membre reconnu par la pointeuse mais refusé par elle (période de validité dépassée…). */
    public function refus(string $empreinteId, Lecteur $lecteur, CarbonInterface $quand): Passage
    {
        $existant = Passage::where('lecteur_id', $lecteur->id)->where('empreinte_id', $empreinteId)->where('passe_le', $quand)->first();
        if ($existant) {
            return $existant;
        }

        $client = Client::where('empreinte_id', $empreinteId)->first();
        $motif = $client ? ($this->verifierDroit($client, $quand)[1] ?? 'refus_pointeuse') : 'empreinte_inconnue';

        return $this->enregistrer([
            'client_id' => $client?->id,
            'lecteur_id' => $lecteur->id,
            'methode' => Passage::METHODE_EMPREINTE,
            'statut' => Passage::STATUT_REFUSE,
            'motif' => $motif,
            'empreinte_id' => $empreinteId,
            'passe_le' => $quand,
        ]);
    }

    /** Doigt inconnu de la pointeuse. */
    public function refusInconnu(Lecteur $lecteur, CarbonInterface $quand): Passage
    {
        return Passage::firstOrCreate(
            ['lecteur_id' => $lecteur->id, 'empreinte_id' => null, 'passe_le' => $quand, 'motif' => 'empreinte_inconnue'],
            ['methode' => Passage::METHODE_EMPREINTE, 'statut' => Passage::STATUT_REFUSE],
        );
    }

    /**
     * Passage validé par la caissière après encaissement d'un journalier.
     * Le ticket vaut arrivée, sauf pour un client qui a une empreinte : c'est alors
     * son badge sur la pointeuse qui marque l'arrivée (puis le départ).
     */
    public function parCaisse(Paiement $paiement, User $caissier, bool $avecClient = true): Passage
    {
        $client = $avecClient ? $paiement->client : null;

        return $this->enregistrer([
            'client_id' => $client?->id,
            'user_id' => $caissier->id,
            'paiement_id' => $paiement->id,
            'methode' => Passage::METHODE_CAISSE,
            'statut' => Passage::STATUT_AUTORISE,
            'sens' => $client?->empreinte_id ? null : Passage::SENS_ENTREE,
            'passe_le' => now(),
        ]);
    }

    /**
     * 1er badge autorisé du jour = arrivée ; départ = badge suivant fait à partir de l'heure
     * de fin des séances du jour (Paramètres) ; tout autre badge = séance déjà enregistrée.
     * Passe « 1 séance par jour » : le 2e badge est toujours le départ, à toute heure.
     */
    private function sens(Client $client, CarbonInterface $quand): string
    {
        $dejaBadge = Passage::where('client_id', $client->id)
            ->where('statut', Passage::STATUT_AUTORISE)
            ->whereIn('sens', [Passage::SENS_ENTREE, Passage::SENS_DEPART])
            ->whereBetween('passe_le', [$quand->copy()->startOfDay(), $quand->copy()->endOfDay()])
            ->count();

        if ($dejaBadge === 0) {
            return Passage::SENS_ENTREE;
        }

        // Le départ ne se badge qu'à partir de l'heure de fin des séances (si elle est renseignée)
        $fin = Horaires::finDuJour($quand);
        $uneSeanceParJour = (bool) $client->abonnementLe($quand)?->formule?->uneSeanceParJour();
        if ($dejaBadge === 1 && ($uneSeanceParJour || $fin === null || $quand->format('H:i') >= $fin)) {
            return Passage::SENS_DEPART;
        }

        return Passage::SENS_DEJA;
    }

    /**
     * Droit d'entrée, dans l'ordre : abonnement en cours, ticket du jour, carnet Fidélité.
     *
     * @return array{0: bool, 1: ?string, 2: ?Paiement} autorisé, motif du refus, carnet à décompter
     */
    private function verifierDroit(Client $client, CarbonInterface $quand): array
    {
        if ($client->type === Client::TYPE_ABONNE && $client->abonnementLe($quand)) {
            return $client->seanceDuJourFaite($quand) ? [false, 'seance_du_jour_faite', null] : [true, null, null];
        }

        // Ticket payé ce jour-là, ou séance de carnet déjà décomptée aujourd'hui (départ, retour)
        if ($client->aPayeJournalierLe($quand) || $client->carnetUtiliseLe($quand)) {
            return [true, null, null];
        }

        if ($carnet = $client->carnetEnCours()) {
            return [true, null, $carnet];
        }

        return match (true) {
            $client->carnets()->isNotEmpty() => [false, 'carnet_epuise', null],
            $client->type === Client::TYPE_ABONNE => [false, 'abonnement_expire', null],
            default => [false, 'paiement_requis', null],
        };
    }

    private function enregistrer(array $attributs): Passage
    {
        return Passage::create($attributs);
    }
}
