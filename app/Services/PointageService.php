<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Lecteur;
use App\Models\Paiement;
use App\Models\Passage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PointageService
{
    public function __construct(private PorteService $porte) {}

    /** Passage via le lecteur d'empreinte. */
    public function parEmpreinte(string $empreinteId, ?Lecteur $lecteur = null): Passage
    {
        $client = Client::where('empreinte_id', $empreinteId)->first();

        return $this->pointer($client, Passage::METHODE_EMPREINTE, $empreinteId, 'empreinte_inconnue', $lecteur);
    }

    /** Passage par badge RFID ou QR code (n° de carte ou code d'accès personnel). */
    public function parCarte(string $code, ?Lecteur $lecteur = null, ?int $salleId = null): Passage
    {
        $code = trim($code);
        $client = Client::where('carte_id', $code)->orWhere('code_acces', strtoupper($code))->first();

        return $this->pointer($client, Passage::METHODE_CARTE, $code, 'carte_inconnue', $lecteur, $salleId);
    }

    /** Passage validé par la caissière après encaissement d'un journalier. */
    public function parCaisse(Paiement $paiement, ?User $caissier): Passage
    {
        return $this->enregistrer([
            'client_id' => $paiement->client_id,
            'user_id' => $caissier?->id,
            'paiement_id' => $paiement->id,
            'salle_id' => $paiement->caisse?->salle_id,
            'methode' => Passage::METHODE_CAISSE,
            'statut' => Passage::STATUT_AUTORISE,
        ]);
    }

    private function pointer(?Client $client, string $methode, string $identifiant, string $motifInconnu, ?Lecteur $lecteur, ?int $salleId = null): Passage
    {
        $salleId ??= $lecteur?->salle_id;

        if (! $client) {
            return $this->enregistrer([
                'lecteur_id' => $lecteur?->id,
                'salle_id' => $salleId,
                'methode' => $methode,
                'statut' => Passage::STATUT_REFUSE,
                'motif' => $motifInconnu,
                'empreinte_id' => $identifiant,
            ]);
        }

        // Anti-doublon : un client qui repose le doigt ou repasse sa carte ne crée qu'un passage
        $recent = Passage::where('client_id', $client->id)
            ->where('statut', Passage::STATUT_AUTORISE)
            ->where('passe_le', '>=', now()->subSeconds((int) config('salle.anti_doublon_secondes')))
            ->latest('passe_le')
            ->first();

        if ($recent) {
            return $recent;
        }

        $passage = DB::transaction(function () use ($client, $methode, $identifiant, $lecteur, $salleId) {
            [$autorise, $motif] = $this->verifierDroit($client);

            return $this->enregistrer([
                'client_id' => $client->id,
                'lecteur_id' => $lecteur?->id,
                'salle_id' => $salleId,
                'methode' => $methode,
                'statut' => $autorise ? Passage::STATUT_AUTORISE : Passage::STATUT_REFUSE,
                'motif' => $motif,
                'empreinte_id' => $identifiant,
            ]);
        });

        $this->porte->ouvrir($passage);

        return $passage;
    }

    /**
     * Droit d'entrée. Un abonnement à la durée prime ; sinon un carnet est décompté d'une entrée.
     *
     * @return array{0: bool, 1: ?string}
     */
    private function verifierDroit(Client $client): array
    {
        if ($client->gelEnCours()) {
            return [false, 'abonnement_gele'];
        }

        if ($client->type === Client::TYPE_ABONNE) {
            $abonnement = $client->abonnementActif();

            if (! $abonnement) {
                $carnetVide = $client->abonnements()->enCours()->where('entrees_restantes', 0)->exists();

                return [false, $carnetVide ? 'carnet_epuise' : 'abonnement_expire'];
            }

            if ($abonnement->estCarnet()) {
                $abonnement->decrement('entrees_restantes');
            }

            return [true, null];
        }

        // Journalier enrôlé : il doit avoir payé aujourd'hui
        $aPaye = $client->paiements()->valides()
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
