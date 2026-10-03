<?php

namespace App\Services;

use App\Models\Client;
use App\Models\CommandePointeuse;
use App\Models\Lecteur;
use Illuminate\Support\Str;

/**
 * Tient la liste des membres de la pointeuse à jour depuis l'application :
 * nouveau client → son nom s'affiche sur la pointeuse, client archivé → retiré.
 * Les commandes sont mises en file ; la pointeuse les récupère à sa prochaine connexion.
 */
class SynchroPointeuseService
{
    public function ajouterOuModifier(Client $client): void
    {
        if (! $client->empreinte_id) {
            return;
        }

        $this->pourChaquePointeuse($client->empreinte_id, sprintf(
            "DATA UPDATE USERINFO PIN=%s\tName=%s\tPri=0\tPasswd=\tCard=\tGrp=1\tTZ=0000000000000000",
            $client->empreinte_id,
            self::nomPourPointeuse($client->nom_complet),
        ));
    }

    public function retirer(string $empreinteId): void
    {
        $this->pourChaquePointeuse($empreinteId, "DATA DELETE USERINFO PIN={$empreinteId}");
    }

    /** Envoie toute la liste des membres (après l'installation d'une nouvelle pointeuse). */
    public function toutSynchroniser(Lecteur $lecteur): int
    {
        $nombre = 0;

        Client::whereNotNull('empreinte_id')->orderBy('id')->each(function (Client $client) use ($lecteur, &$nombre) {
            $this->mettreEnFile($lecteur, $client->empreinte_id, sprintf(
                "DATA UPDATE USERINFO PIN=%s\tName=%s\tPri=0\tPasswd=\tCard=\tGrp=1\tTZ=0000000000000000",
                $client->empreinte_id,
                self::nomPourPointeuse($client->nom_complet),
            ));
            $nombre++;
        });

        return $nombre;
    }

    /**
     * Nom affiché sur l'écran de la pointeuse : ASCII, 24 caractères max.
     * Les tabulations et retours à la ligne sont retirés : ils serviraient
     * sinon à injecter des champs ou des commandes dans le protocole.
     */
    public static function nomPourPointeuse(string $nom): string
    {
        $propre = preg_replace("/[^A-Za-z0-9 .'-]/", '', Str::ascii($nom));

        return mb_substr(trim(preg_replace('/\s+/', ' ', (string) $propre)), 0, 24) ?: 'Membre';
    }

    private function pourChaquePointeuse(string $empreinteId, string $commande): void
    {
        Lecteur::pointeuses()->each(fn (Lecteur $l) => $this->mettreEnFile($l, $empreinteId, $commande));
    }

    private function mettreEnFile(Lecteur $lecteur, string $empreinteId, string $commande): void
    {
        // Une commande encore en attente pour ce membre est remplacée par la plus récente
        $lecteur->commandes()->where('statut', CommandePointeuse::EN_ATTENTE)->get()
            ->filter(fn (CommandePointeuse $c) => preg_match('/\bPIN='.preg_quote($empreinteId, '/').'(\t|$)/', $c->commande))
            ->each->delete();

        $lecteur->commandes()->create(['commande' => $commande, 'statut' => CommandePointeuse::EN_ATTENTE]);
    }
}
