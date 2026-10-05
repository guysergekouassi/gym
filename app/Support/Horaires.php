<?php

namespace App\Support;

use App\Models\Parametre;
use Carbon\CarbonInterface;

/**
 * Horaires des séances par jour de la semaine (page Paramètres), imprimés sur les tickets.
 * Pour chaque jour : non défini, fermé, ou ouvert de … à ….
 */
final class Horaires
{
    public const CLE = 'horaires';

    public const OUVERT = 'ouvert';
    public const FERME = 'ferme';

    public const JOURS = [1 => 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

    private const COURTS = [1 => 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

    /** Heures proposées dans les listes : toutes les 30 minutes. */
    public static function heures(): array
    {
        $heures = [];
        for ($m = 0; $m < 24 * 60; $m += 30) {
            $heures[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        }

        return $heures;
    }

    /** @return array<int, array{etat: string, debut?: string, fin?: string}> jours définis, clé 1 (lundi) à 7 */
    public static function tous(): array
    {
        $brut = json_decode((string) Parametre::valeur(self::CLE, ''), true);
        if (! is_array($brut)) {
            return [];
        }

        $horaires = [];
        foreach (self::JOURS as $n => $nom) {
            $jour = $brut[$n] ?? null;
            if (($jour['etat'] ?? null) === self::FERME) {
                $horaires[$n] = ['etat' => self::FERME];
            } elseif (($jour['etat'] ?? null) === self::OUVERT && isset($jour['debut'], $jour['fin'])) {
                $horaires[$n] = ['etat' => self::OUVERT, 'debut' => (string) $jour['debut'], 'fin' => (string) $jour['fin']];
            }
        }

        return $horaires;
    }

    public static function enregistrer(array $horaires): void
    {
        Parametre::definir(self::CLE, $horaires === [] ? '' : json_encode($horaires));
    }

    /** Heure de fin des séances ce jour-là (« 22:00 »), ou null si le jour n'est pas « Ouvert ». */
    public static function finDuJour(CarbonInterface $date): ?string
    {
        $jour = self::tous()[$date->dayOfWeekIso] ?? null;

        return ($jour['etat'] ?? null) === self::OUVERT ? $jour['fin'] : null;
    }

    /** La salle est-elle fermée à cet instant (après l'heure de fin, ou jour fermé) ? Inconnu (non défini) = non. */
    public static function estFermeA(CarbonInterface $instant): bool
    {
        $jour = self::tous()[$instant->dayOfWeekIso] ?? null;

        return match ($jour['etat'] ?? null) {
            self::FERME => true,
            self::OUVERT => $instant->format('H:i') >= $jour['fin'],
            default => false,
        };
    }

    /** « 06:00 – 22:00 », « Fermé », ou null si le jour n'est pas renseigné. */
    public static function duJour(CarbonInterface $date): ?string
    {
        $jour = self::tous()[$date->dayOfWeekIso] ?? null;

        return match ($jour['etat'] ?? null) {
            self::OUVERT => $jour['debut'].' – '.$jour['fin'],
            self::FERME => 'Fermé',
            default => null,
        };
    }

    /**
     * Semaine résumée, jours consécutifs identiques regroupés :
     * ["Lun–Ven 06:00 – 22:00", "Sam 08:00 – 14:00", "Dim Fermé"].
     *
     * @return list<string>
     */
    public static function resume(): array
    {
        $tous = self::tous();
        $lignes = [];
        $groupe = null;

        foreach (self::JOURS as $n => $nom) {
            $texte = match ($tous[$n]['etat'] ?? null) {
                self::OUVERT => $tous[$n]['debut'].' – '.$tous[$n]['fin'],
                self::FERME => 'Fermé',
                default => null,
            };

            if ($groupe && $groupe['texte'] === $texte && $groupe['au'] === $n - 1) {
                $groupe['au'] = $n;

                continue;
            }
            if ($groupe) {
                $lignes[] = $groupe;
            }
            $groupe = $texte === null ? null : ['du' => $n, 'au' => $n, 'texte' => $texte];
        }
        if ($groupe) {
            $lignes[] = $groupe;
        }

        return array_map(fn ($g) => self::COURTS[$g['du']].($g['au'] > $g['du'] ? '–'.self::COURTS[$g['au']] : '').' '.$g['texte'], $lignes);
    }
}
