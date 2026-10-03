<?php

namespace App\Services;

use App\Models\Client;
use App\Models\CommandePointeuse;
use App\Models\Lecteur;
use Illuminate\Support\Str;

/**
 * Tient la liste des membres de la pointeuse à jour : nouveau client, abonnement payé
 * ou annulé, client archivé… Les demandes sont mises en file ; le programme d'écoute
 * (php artisan pointeuse:ecouter) les envoie à la pointeuse, même après une coupure.
 */
class SynchroPointeuseService
{
    public function ajouterOuModifier(Client $client): void
    {
        if (! $client->empreinte_id) {
            return;
        }

        Lecteur::pointeuses()->each(fn (Lecteur $l) => $this->mettreEnFile($l, $client->empreinte_id, [
            'action' => 'utilisateur', 'client_id' => $client->id,
        ]));
    }

    public function retirer(string $empreinteId): void
    {
        Lecteur::pointeuses()->each(fn (Lecteur $l) => $this->mettreEnFile($l, $empreinteId, [
            'action' => 'supprimer', 'numero' => $empreinteId,
        ]));
    }

    /** Envoie toute la liste des membres (nouvelle pointeuse, ou pointeuse réinitialisée). */
    public function toutSynchroniser(Lecteur $lecteur): int
    {
        $nombre = 0;
        Client::whereNotNull('empreinte_id')->orderBy('id')->each(function (Client $c) use ($lecteur, &$nombre) {
            $this->mettreEnFile($lecteur, $c->empreinte_id, ['action' => 'utilisateur', 'client_id' => $c->id]);
            $nombre++;
        });

        return $nombre;
    }

    /** Nom affiché sur l'écran de la pointeuse : lettres simples, 24 caractères max. */
    public static function nomPourPointeuse(string $nom): string
    {
        $propre = preg_replace("/[^A-Za-z0-9 .'-]/", '', Str::ascii($nom));

        return mb_substr(trim(preg_replace('/\s+/', ' ', (string) $propre)), 0, 24) ?: 'Membre';
    }

    private function mettreEnFile(Lecteur $lecteur, string $numero, array $commande): void
    {
        $commande['numero'] = $numero;

        // Une demande encore en attente pour ce membre est remplacée par la plus récente
        $lecteur->commandes()->where('statut', CommandePointeuse::EN_ATTENTE)->get()
            ->filter(fn (CommandePointeuse $c) => (json_decode($c->commande, true)['numero'] ?? null) === $numero)
            ->each->delete();

        $lecteur->commandes()->create([
            'commande' => json_encode($commande, JSON_THROW_ON_ERROR),
            'statut' => CommandePointeuse::EN_ATTENTE,
        ]);
    }
}
