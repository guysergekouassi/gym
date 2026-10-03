<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Lecteur;
use App\Models\Paiement;
use App\Models\Passage;
use App\Models\User;

class PointageService
{
    /**
     * Passage au lecteur de badge : soit le boîtier réseau (API + token),
     * soit le lecteur USB branché sur le poste d'accueil (session caisse).
     */
    public function parBadge(string $badgeId, ?Lecteur $lecteur = null, ?User $poste = null): Passage
    {
        $client = Client::where('badge_id', $badgeId)->first();

        if (! $client) {
            return $this->enregistrer([
                'lecteur_id' => $lecteur?->id,
                'user_id' => $poste?->id,
                'methode' => Passage::METHODE_BADGE,
                'statut' => Passage::STATUT_REFUSE,
                'motif' => 'badge_inconnu',
                'badge_id' => $badgeId,
            ]);
        }

        // Anti-doublon : un client qui repasse son badge plusieurs fois ne crée qu'un passage
        $recent = Passage::where('client_id', $client->id)
            ->where('statut', Passage::STATUT_AUTORISE)
            ->where('passe_le', '>=', now()->subSeconds((int) config('salle.anti_doublon_secondes')))
            ->latest('passe_le')
            ->first();

        if ($recent) {
            return $recent;
        }

        [$autorise, $motif] = $this->verifierDroit($client);

        return $this->enregistrer([
            'client_id' => $client->id,
            'lecteur_id' => $lecteur?->id,
            'user_id' => $poste?->id,
            'methode' => Passage::METHODE_BADGE,
            'statut' => $autorise ? Passage::STATUT_AUTORISE : Passage::STATUT_REFUSE,
            'motif' => $motif,
            'badge_id' => $badgeId,
        ]);
    }

    /** Passage validé par la caissière après encaissement d'un journalier. */
    public function parCaisse(Paiement $paiement, User $caissier): Passage
    {
        return $this->enregistrer([
            'client_id' => $paiement->client_id,
            'user_id' => $caissier->id,
            'paiement_id' => $paiement->id,
            'methode' => Passage::METHODE_CAISSE,
            'statut' => Passage::STATUT_AUTORISE,
        ]);
    }

    /** @return array{0: bool, 1: ?string} */
    private function verifierDroit(Client $client): array
    {
        if ($client->type === Client::TYPE_ABONNE) {
            return $client->abonnementActif() ? [true, null] : [false, 'abonnement_expire'];
        }

        // Journalier badgé : il doit avoir payé (et non annulé) aujourd'hui
        $aPaye = $client->paiements()
            ->valides()
            ->where('type', Paiement::TYPE_JOURNALIER)
            ->whereDate('created_at', today()->toDateString())
            ->exists();

        return $aPaye ? [true, null] : [false, 'paiement_requis'];
    }

    private function enregistrer(array $attributs): Passage
    {
        return Passage::create($attributs + ['passe_le' => now()]);
    }
}
