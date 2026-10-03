<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Lecteur;
use App\Models\Paiement;
use App\Models\Passage;
use App\Models\User;
use Carbon\CarbonInterface;

class PointageService
{
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

        [$autorise, $motif] = $this->verifierDroit($client, $quand);

        return $this->enregistrer([
            'client_id' => $client->id,
            'lecteur_id' => $lecteur?->id,
            'user_id' => $poste?->id,
            'methode' => Passage::METHODE_EMPREINTE,
            'statut' => $autorise ? Passage::STATUT_AUTORISE : Passage::STATUT_REFUSE,
            'motif' => $motif,
            'empreinte_id' => $empreinteId,
            'passe_le' => $quand,
        ]);
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

    /** Passage validé par la caissière après encaissement d'un journalier. */
    public function parCaisse(Paiement $paiement, User $caissier, bool $avecClient = true): Passage
    {
        return $this->enregistrer([
            'client_id' => $avecClient ? $paiement->client_id : null,
            'user_id' => $caissier->id,
            'paiement_id' => $paiement->id,
            'methode' => Passage::METHODE_CAISSE,
            'statut' => Passage::STATUT_AUTORISE,
            'passe_le' => now(),
        ]);
    }

    /** @return array{0: bool, 1: ?string} */
    private function verifierDroit(Client $client, CarbonInterface $quand): array
    {
        if ($client->type === Client::TYPE_ABONNE) {
            $valide = $client->abonnements()->enCours($quand)->exists();

            return $valide ? [true, null] : [false, 'abonnement_expire'];
        }

        // Journalier enrôlé : il doit avoir payé (et non annulé) le jour même
        $aPaye = $client->paiements()
            ->valides()
            ->where('type', Paiement::TYPE_JOURNALIER)
            ->whereDate('created_at', $quand->toDateString())
            ->exists();

        return $aPaye ? [true, null] : [false, 'paiement_requis'];
    }

    private function enregistrer(array $attributs): Passage
    {
        return Passage::create($attributs);
    }
}
