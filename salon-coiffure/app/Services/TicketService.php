<?php

namespace App\Services;

use App\Models\Vente;
use App\Support\Fcfa;
use Illuminate\Support\Str;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;
use RuntimeException;

/**
 * Impression directe ESC/POS sur l'imprimante thermique (mode TICKET_DRIVER=escpos).
 * Le mode par défaut (navigateur) n'utilise pas cette classe.
 */
class TicketService
{
    public function imprimer(Vente $vente): void
    {
        if (! class_exists(Printer::class)) {
            throw new RuntimeException('Le package mike42/escpos-php n\'est pas installé.');
        }

        $vente->loadMissing(['lignes', 'user']);
        $config = config('salon.ticket');
        $colonnes = $config['largeur_mm'] === 58 ? 32 : 42;

        $connecteur = match ($config['connecteur']) {
            'network' => new NetworkPrintConnector($config['cible'], $config['port']),
            'windows' => new WindowsPrintConnector($config['cible']),
            'fichier' => new FilePrintConnector($config['cible']),
            default => throw new RuntimeException("Connecteur d'impression inconnu : {$config['connecteur']}"),
        };

        // Beaucoup d'imprimantes thermiques gèrent mal les accents : on les retire
        $t = fn (?string $texte) => Str::ascii((string) $texte);
        $ligne = fn (string $gauche, string $droite) => $t($gauche).str_repeat(' ', max(1, $colonnes - mb_strlen($t($gauche)) - mb_strlen($t($droite)))).$t($droite)."\n";

        $imprimante = new Printer($connecteur);

        try {
            $imprimante->setJustification(Printer::JUSTIFY_CENTER);
            $imprimante->setEmphasis(true);
            $imprimante->text($t(config('salon.nom'))."\n");
            $imprimante->setEmphasis(false);
            $imprimante->text($t(config('salon.adresse'))."\n");
            if (config('salon.telephone')) {
                $imprimante->text('Tel : '.$t(config('salon.telephone'))."\n");
            }
            $imprimante->text(str_repeat('-', $colonnes)."\n");

            $imprimante->setJustification(Printer::JUSTIFY_LEFT);
            $imprimante->text('Ticket : '.$vente->numero."\n");
            $imprimante->text('Date   : '.$vente->created_at->format('d/m/Y H:i')."\n");
            $imprimante->text(str_repeat('-', $colonnes)."\n");

            foreach ($vente->lignes as $l) {
                $imprimante->text($t(Str::limit($l->libelle, $colonnes, ''))."\n");
                $imprimante->text($ligne('  '.$l->quantite.' x '.Fcfa::format($l->prix_unitaire), Fcfa::format($l->total)));
            }
            $imprimante->text(str_repeat('-', $colonnes)."\n");

            $imprimante->setEmphasis(true);
            $imprimante->setTextSize(1, 2);
            $imprimante->text($ligne('TOTAL', Fcfa::format($vente->total)));
            $imprimante->setTextSize(1, 1);
            $imprimante->setEmphasis(false);
            $imprimante->text($ligne('Paiement', $vente->libelleModePaiement()));
            if ($vente->montant_recu !== null) {
                $imprimante->text($ligne('Recu', Fcfa::format($vente->montant_recu)));
                $imprimante->text($ligne('Monnaie rendue', Fcfa::format($vente->monnaie_rendue)));
            }
            $imprimante->text(str_repeat('-', $colonnes)."\n");

            $imprimante->setJustification(Printer::JUSTIFY_CENTER);
            $imprimante->text('Servi par : '.$t($vente->user?->name)."\n");
            $imprimante->text($t(config('salon.message_ticket'))."\n");
            $imprimante->feed(3);
            $imprimante->cut();
        } finally {
            $imprimante->close();
        }
    }
}
