<?php

namespace App\Services;

use App\Models\Abonnement;
use App\Models\Client;
use App\Models\Formule;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CaisseService
{
    public function __construct(private PointageService $pointage) {}

    /**
     * Encaisse une entrée journalière, enregistre le passage et numérote le reçu.
     *
     * @param  array{client_id?: ?int, nom?: ?string, telephone?: ?string, montant: int, mode: string, reference?: ?string}  $data
     */
    public function encaisserJournalier(array $data, User $caissier): Paiement
    {
        return DB::transaction(function () use ($data, $caissier) {
            $client = $this->resoudreClientJournalier($data);

            $paiement = Paiement::create([
                'client_id' => $client?->id,
                'user_id' => $caissier->id,
                'type' => Paiement::TYPE_JOURNALIER,
                'montant' => $data['montant'],
                'mode' => $data['mode'],
                'reference' => $data['reference'] ?? null,
            ]);

            $this->numeroter($paiement);
            $this->pointage->parCaisse($paiement, $caissier);

            return $paiement;
        });
    }

    /**
     * Souscription ou renouvellement. Si un abonnement est en cours,
     * le nouveau démarre le lendemain de sa fin (aucun jour perdu).
     */
    public function souscrireAbonnement(Client $client, Formule $formule, array $data, User $caissier): Paiement
    {
        return DB::transaction(function () use ($client, $formule, $data, $caissier) {
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
