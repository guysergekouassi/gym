<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Période choisie dans le filtre des tableaux de bord.
 *
 * Mode « calendrier » : exercice (année) → mois → semaine du mois → jour.
 *   Une semaine ou un jour ne peuvent être choisis que si un mois l'est ;
 *   un jour choisi avec une semaine doit appartenir à cette semaine.
 * Mode « période » : du … au (dates libres).
 */
final class Periode
{
    public const MOIS = [1 => 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

    private const JOURS_COURTS = [1 => 'Lun.', 'Mar.', 'Mer.', 'Jeu.', 'Ven.', 'Sam.', 'Dim.'];

    private function __construct(
        public readonly string $mode,          // calendrier | periode
        public readonly string $granularite,   // jour | semaine | mois | annee | periode
        public readonly CarbonImmutable $du,
        public readonly CarbonImmutable $au,
        public readonly int $annee,
        public readonly ?int $mois = null,
        public readonly ?int $semaine = null,
        public readonly ?int $jour = null,
    ) {}

    /** Par défaut : aujourd'hui. Les valeurs invalides ou incohérentes sont corrigées, jamais exécutées. */
    public static function depuisRequete(Request $request): self
    {
        $aujourdhui = CarbonImmutable::today();

        if ($request->query('mode') === 'periode') {
            $du = self::date($request->query('du')) ?? $aujourdhui->startOfMonth();
            $au = self::date($request->query('au')) ?? $aujourdhui;
            if ($au->lt($du)) {
                [$du, $au] = [$au, $du];
            }
            if ($du->diffInDays($au) > 366 * 3) {
                $du = $au->subYears(3); // période limitée à 3 ans (requêtes raisonnables)
            }

            return new self('periode', 'periode', $du, $au, $au->year);
        }

        $sansFiltre = ! $request->hasAny(['annee', 'mois', 'semaine', 'jour']);
        $annee = self::entier($request->query('annee'), 2000, $aujourdhui->year + 1) ?? $aujourdhui->year;
        $mois = $sansFiltre ? $aujourdhui->month : self::entier($request->query('mois'), 1, 12);
        $semaine = $mois ? self::entier($request->query('semaine'), 1, 6) : null;
        $jour = $mois ? ($sansFiltre ? $aujourdhui->day : self::entier($request->query('jour'), 1, 31)) : null;

        if ($mois === null) {
            $debut = CarbonImmutable::create($annee, 1, 1);

            return new self('calendrier', 'annee', $debut, $debut->endOfYear()->startOfDay(), $annee);
        }

        $debutMois = CarbonImmutable::create($annee, $mois, 1);
        $semaines = self::semainesDuMois($annee, $mois);
        if ($semaine !== null && ! isset($semaines[$semaine])) {
            $semaine = null;
        }
        if ($jour !== null && $jour > $debutMois->daysInMonth) {
            $jour = null;
        }
        if ($jour !== null && $semaine !== null) {
            $date = $debutMois->setDay($jour);
            if ($date->lt($semaines[$semaine]['du']) || $date->gt($semaines[$semaine]['au'])) {
                $jour = null; // jour hors de la semaine choisie
            }
        }

        if ($jour !== null) {
            $date = $debutMois->setDay($jour);

            return new self('calendrier', 'jour', $date, $date, $annee, $mois, $semaine, $jour);
        }
        if ($semaine !== null) {
            return new self('calendrier', 'semaine', $semaines[$semaine]['du'], $semaines[$semaine]['au'], $annee, $mois, $semaine);
        }

        return new self('calendrier', 'mois', $debutMois, $debutMois->endOfMonth()->startOfDay(), $annee, $mois);
    }

    /**
     * Semaines d'un mois (lundi → dimanche), la première et la dernière coupées aux bords du mois.
     *
     * @return array<int, array{du: CarbonImmutable, au: CarbonImmutable, libelle: string}>
     */
    public static function semainesDuMois(int $annee, int $mois): array
    {
        $debut = CarbonImmutable::create($annee, $mois, 1);
        $fin = $debut->endOfMonth()->startOfDay();
        $semaines = [];
        $n = 1;

        for ($du = $debut; $du->lte($fin); $n++) {
            $au = $du->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay()->min($fin);
            $semaines[$n] = ['du' => $du, 'au' => $au, 'libelle' => "Semaine {$n} · {$du->day}–{$au->day}"];
            $du = $au->addDay();
        }

        return $semaines;
    }

    /** Jours proposés : ceux du mois, ou seulement ceux de la semaine choisie. */
    public function joursProposes(): array
    {
        if ($this->mois === null) {
            return [];
        }

        $semaines = self::semainesDuMois($this->annee, $this->mois);
        $du = $this->semaine ? $semaines[$this->semaine]['du'] : CarbonImmutable::create($this->annee, $this->mois, 1);
        $au = $this->semaine ? $semaines[$this->semaine]['au'] : $du->endOfMonth()->startOfDay();

        $jours = [];
        for ($d = $du; $d->lte($au); $d = $d->addDay()) {
            $jours[$d->day] = self::JOURS_COURTS[$d->dayOfWeekIso].' '.$d->day;
        }

        return $jours;
    }

    /** Période précédente de même nature, pour les comparaisons (hier, mois dernier…). */
    public function precedente(): self
    {
        return match ($this->granularite) {
            'jour' => new self($this->mode, 'jour', $this->du->subDay(), $this->du->subDay(), $this->annee),
            'mois' => new self($this->mode, 'mois', $this->du->subMonthNoOverflow(), $this->du->subMonthNoOverflow()->endOfMonth()->startOfDay(), $this->annee),
            'annee' => new self($this->mode, 'annee', $this->du->subYear(), $this->au->subYear(), $this->annee - 1),
            default => new self($this->mode, $this->granularite, $this->du->subDays($this->nombreDeJours()), $this->du->subDay(), $this->annee),
        };
    }

    /** La période est en cours (elle contient l'instant présent). */
    public function estEnCours(): bool
    {
        return CarbonImmutable::now()->between($this->du->startOfDay(), $this->au->endOfDay());
    }

    /**
     * Instant de la période précédente qui correspond à « maintenant » dans la période en cours
     * (même temps écoulé depuis le début). Null si la période n'est pas en cours.
     */
    public function instantComparable(): ?CarbonImmutable
    {
        if (! $this->estEnCours()) {
            return null;
        }

        $ecoule = (int) $this->du->startOfDay()->diffInSeconds(CarbonImmutable::now());

        return $this->precedente()->du->startOfDay()->addSeconds($ecoule);
    }

    public function nombreDeJours(): int
    {
        return (int) $this->du->diffInDays($this->au) + 1;
    }

    /** Date de référence pour un état « à date » (jamais dans le futur). */
    public function dateReference(): CarbonImmutable
    {
        return $this->au->min(CarbonImmutable::today());
    }

    /** « du jour », « de la semaine »… pour les titres des chiffres clés. */
    public function suffixe(): string
    {
        return match ($this->granularite) {
            'jour' => $this->du->isToday() ? 'du jour' : 'du '.$this->du->format('d/m/Y'),
            'semaine' => 'de la semaine',
            'mois' => 'du mois',
            'annee' => "de l'année",
            default => 'de la période',
        };
    }

    public function reference(): string
    {
        $texte = match ($this->granularite) {
            'jour' => 'par rapport à la veille',
            'semaine' => 'par rapport aux jours précédents',
            'mois' => 'par rapport au mois précédent',
            'annee' => "par rapport à l'année précédente",
            default => 'par rapport à la période précédente',
        };

        if ($this->estEnCours()) {
            $texte .= $this->granularite === 'jour' ? ' à la même heure' : ' à la même date';
        }

        return $texte;
    }

    public function libelle(): string
    {
        return match ($this->granularite) {
            'jour' => $this->du->translatedFormat('l j F Y'),
            'semaine' => 'Du '.$this->du->format('d/m').' au '.$this->au->format('d/m/Y'),
            'mois' => self::MOIS[$this->mois].' '.$this->annee,
            'annee' => 'Exercice '.$this->annee,
            default => 'Du '.$this->du->format('d/m/Y').' au '.$this->au->format('d/m/Y'),
        };
    }

    /** Paramètres d'URL de cette période (liens d'export, pagination…). */
    public function parametres(): array
    {
        return $this->mode === 'periode'
            ? ['mode' => 'periode', 'du' => $this->du->toDateString(), 'au' => $this->au->toDateString()]
            : array_filter(['annee' => $this->annee, 'mois' => $this->mois ?? '', 'semaine' => $this->semaine, 'jour' => $this->jour], fn ($v) => $v !== null);
    }

    private static function date(mixed $valeur): ?CarbonImmutable
    {
        if (! is_string($valeur) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur)) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $valeur);
        } catch (\Throwable) {
            return null;
        }

        return $date && $date->year >= 2000 && $date->year <= CarbonImmutable::today()->year + 1 ? $date : null;
    }

    private static function entier(mixed $valeur, int $min, int $max): ?int
    {
        if (! is_string($valeur) || ! ctype_digit($valeur)) {
            return null;
        }

        $n = (int) $valeur;

        return $n >= $min && $n <= $max ? $n : null;
    }
}
