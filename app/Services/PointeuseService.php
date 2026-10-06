<?php

namespace App\Services;

use App\Models\Client;
use App\Models\CommandePointeuse;
use App\Models\Lecteur;
use App\Models\Passage;
use App\Services\Hikvision\HikvisionClient;
use App\Services\Hikvision\PointeuseInjoignable;
use App\Support\Empreinte;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/** Le travail fait en continu par « php artisan pointeuse:ecouter » pour chaque pointeuse. */
class PointeuseService
{
    /** Codes Hikvision (événements d'accès) : reconnu par empreinte, carte, visage… */
    public const SUCCES = [1, 38, 42, 45, 48, 75, 80, 104];

    /** Doigt / carte / visage non reconnus. */
    public const INCONNU = [9, 39, 40, 76];

    public function __construct(private PointageService $pointage) {}

    public function client(Lecteur $lecteur): HikvisionClient
    {
        return new HikvisionClient($lecteur);
    }

    /** Test de connexion : met à jour modèle et n° de série. */
    public function tester(Lecteur $lecteur): array
    {
        $infos = $this->client($lecteur)->infos();
        $lecteur->forceFill([
            'modele' => $infos['modele'],
            'numero_serie' => $infos['numero_serie'] ?? $lecteur->numero_serie,
            'derniere_activite_at' => now(),
            'derniere_erreur' => null,
        ])->save();

        return $infos;
    }

    /** Un tour d'écoute : envoyer les demandes en attente, puis récupérer les passages. */
    public function cycle(Lecteur $lecteur): void
    {
        try {
            $this->executerCommandes($lecteur);
            $this->recupererPassages($lecteur);
            $lecteur->forceFill(['derniere_activite_at' => now(), 'derniere_erreur' => null])->save();
        } catch (PointeuseInjoignable $e) {
            $lecteur->forceFill(['derniere_erreur' => mb_substr($e->getMessage(), 0, 255)])->save();
        }
    }

    public function executerCommandes(Lecteur $lecteur): void
    {
        $hik = $this->client($lecteur);

        foreach ($lecteur->commandes()->where('statut', CommandePointeuse::EN_ATTENTE)->orderBy('id')->limit(20)->get() as $commande) {
            $donnees = json_decode($commande->commande, true) ?: [];

            try {
                if (($donnees['action'] ?? null) === 'supprimer') {
                    $hik->supprimerUtilisateur((string) $donnees['numero']);
                } elseif (($donnees['action'] ?? null) === 'utilisateur') {
                    $client = Client::find($donnees['client_id'] ?? 0);
                    if ($client?->empreinte_id) {
                        [$debut, $fin] = $this->validite($client);
                        $hik->enregistrerUtilisateur($client->empreinte_id, SynchroPointeuseService::nomPourPointeuse($client->nom_complet), $debut, $fin);
                    }
                }
                $commande->update(['statut' => CommandePointeuse::OK, 'terminee_le' => now()]);
            } catch (PointeuseInjoignable $e) {
                if (str_contains($e->getMessage(), 'injoignable') || str_contains($e->getMessage(), 'mot de passe')) {
                    throw $e; // la pointeuse n'est pas là : on réessaiera au prochain tour
                }
                $commande->update(['statut' => CommandePointeuse::ERREUR, 'retour' => mb_substr($e->getMessage(), 0, 100), 'terminee_le' => now()]);
            }
        }
    }

    public function recupererPassages(Lecteur $lecteur): int
    {
        $maintenant = CarbonImmutable::now();
        $depuis = $lecteur->dernier_evenement_le
            ? CarbonImmutable::instance($lecteur->dernier_evenement_le)->max($maintenant->subDays(2))
            : $maintenant->subMinutes(2);

        $evenements = $this->client($lecteur)->evenements($depuis, $maintenant->addMinute());
        $traites = 0;

        foreach ($evenements as $e) {
            if (Empreinte::estPersonnel($e['employe'])) {
                continue; // employé (n° 900000000 et plus) : il ouvre le menu, ce n'est pas un passage
            }
            if (in_array($e['minor'], self::SUCCES, true) && $e['employe']) {
                $this->pointage->parEmpreinte($e['employe'], $lecteur, null, $e['quand']);
            } elseif ($e['employe']) {
                // Doigt reconnu mais accès refusé par la pointeuse (abonnement terminé…)
                $this->pointage->refus($e['employe'], $lecteur, $e['quand']);
            } elseif (in_array($e['minor'], self::INCONNU, true)) {
                $this->pointage->refusInconnu($lecteur, $e['quand']);
            } else {
                continue; // porte, alarme… : sans rapport avec un membre
            }
            $traites++;
        }

        $dernier = collect($evenements)->max('quand');
        if ($dernier) {
            $lecteur->forceFill(['dernier_evenement_le' => $dernier])->save();
        }

        return $traites;
    }

    /**
     * Période pendant laquelle la pointeuse laisse entrer le membre :
     * abonné → jusqu'à la fin de ses droits (passe 1 séance/jour : fermé après le départ, jusqu'à demain) ;
     * carnet Fidélité → tant qu'il reste des séances ; ticket du jour → aujourd'hui.
     * Sans droit : fin hier (la pointeuse le refuse).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function validite(Client $client): array
    {
        $debut = CarbonImmutable::create(2020, 1, 1);
        $hier = CarbonImmutable::yesterday()->endOfDay();
        $maintenant = CarbonImmutable::now();

        if ($client->type === Client::TYPE_ABONNE && ($fin = $client->finDesDroits())) {
            return [$debut, $client->seanceDuJourFaite($maintenant) ? $hier : CarbonImmutable::instance($fin)->endOfDay()];
        }

        // Le carnet est décompté à chaque arrivée ; une fois vide, la pointeuse est remise à jour
        if ($client->seancesCarnet() > 0) {
            return [$debut, CarbonImmutable::today()->addYear()->endOfDay()];
        }

        $aujourdhui = $client->aPayeJournalierLe($maintenant) || $client->carnetUtiliseLe($maintenant);

        return [$debut, $aujourdhui ? CarbonImmutable::today()->endOfDay() : $hier];
    }
}
