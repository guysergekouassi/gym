<?php

namespace App\Services;

use App\Models\Paiement;
use App\Support\Fcfa;
use Illuminate\Support\Str;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;
use RuntimeException;

/**
 * Impression directe ESC/POS (imprimante thermique 80 mm).
 * Ne fonctionne que si le serveur Laravel voit l'imprimante (serveur local à la salle).
 */
class RecuService
{
    public function imprimer(Paiement $paiement): void
    {
        if (! class_exists(Printer::class)) {
            throw new RuntimeException('Le package mike42/escpos-php n\'est pas installé.');
        }

        $paiement->loadMissing(['client', 'user', 'abonnement.formule']);
        $config = config('salle.impression');

        $connecteur = match ($config['connecteur']) {
            'network' => new NetworkPrintConnector($config['cible'], $config['port']),
            'windows' => new WindowsPrintConnector($config['cible']),
            'fichier' => new FilePrintConnector($config['cible']),
            default => throw new RuntimeException("Connecteur d'impression inconnu : {$config['connecteur']}"),
        };

        // Beaucoup d'imprimantes thermiques gèrent mal les accents : on les retire
        $t = fn (?string $texte) => Str::ascii((string) $texte);

        $imprimante = new Printer($connecteur);

        try {
            $imprimante->setJustification(Printer::JUSTIFY_CENTER);
            $imprimante->setEmphasis(true);
            $imprimante->text($t(config('salle.nom'))."\n");
            $imprimante->setEmphasis(false);
            $imprimante->text($t(config('salle.adresse'))."\n");
            if (config('salle.telephone')) {
                $imprimante->text('Tel : '.$t(config('salle.telephone'))."\n");
            }
            if (config('salle.email')) {
                $imprimante->text($t(config('salle.email'))."\n");
            }
            $imprimante->text(str_repeat('-', 42)."\n");

            $imprimante->setJustification(Printer::JUSTIFY_LEFT);
            $imprimante->text('Recu     : '.$paiement->numero_recu."\n");
            $imprimante->text('Date     : '.$paiement->created_at->format('d/m/Y H:i')."\n");
            $imprimante->text('Client   : '.$t($paiement->client?->nom_complet ?? 'Client journalier')."\n");

            $imprimante->text('Objet    : '.$t($paiement->objet())."\n");

            if ($paiement->abonnement) {
                $imprimante->text('Validite : '.$paiement->abonnement->date_debut->format('d/m/Y')
                    .' au '.$paiement->abonnement->date_fin->format('d/m/Y')."\n");
            }

            $imprimante->text('Paiement : '.$t(Paiement::MODES[$paiement->mode] ?? $paiement->mode)."\n");
            if ($paiement->reference) {
                $imprimante->text('Ref.     : '.$t($paiement->reference)."\n");
            }
            $imprimante->text(str_repeat('-', 42)."\n");

            $imprimante->setJustification(Printer::JUSTIFY_CENTER);
            $imprimante->setTextSize(2, 2);
            $imprimante->text($t(Fcfa::format($paiement->montant))."\n");
            $imprimante->setTextSize(1, 1);
            $imprimante->text('Caisse : '.$t($paiement->user?->name)."\n\n");
            $imprimante->text($t(config('salle.message_recu') ?: 'Merci et bonne seance !')."\n");
            $imprimante->feed(3);
            $imprimante->cut();
        } finally {
            $imprimante->close();
        }
    }
}
