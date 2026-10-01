<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** Intervalle de dates [debut, fin] utilisé par les KPI */
class Periode
{
    public function __construct(
        public readonly string $type,
        public readonly CarbonImmutable $debut,
        public readonly CarbonImmutable $fin,
    ) {}

    public static function depuis(string $type, ?string $du = null, ?string $au = null): self
    {
        $maintenant = CarbonImmutable::now();

        return match ($type) {
            'semaine' => new self('semaine', $maintenant->startOfWeek(CarbonImmutable::MONDAY), $maintenant->endOfWeek(CarbonImmutable::SUNDAY)),
            'mois' => new self('mois', $maintenant->startOfMonth(), $maintenant->endOfMonth()),
            'dates' => self::dates($du, $au),
            default => new self('jour', $maintenant->startOfDay(), $maintenant->endOfDay()),
        };
    }

    private static function dates(?string $du, ?string $au): self
    {
        try {
            $debut = CarbonImmutable::parse($du ?: 'today')->startOfDay();
            $fin = CarbonImmutable::parse($au ?: $du ?: 'today')->endOfDay();
        } catch (\Throwable) {
            return self::depuis('jour');
        }

        if ($fin->lessThan($debut)) {
            [$debut, $fin] = [$fin->startOfDay(), $debut->endOfDay()];
        }

        return new self('dates', $debut, $fin);
    }

    /**
     * Période de même durée juste avant, pour calculer l'évolution.
     * Si la période est en cours, on compare à la même durée écoulée
     * (jeudi 18h cette semaine vs jeudi 18h la semaine dernière), sinon la comparaison n'a pas de sens.
     */
    public function precedente(): self
    {
        $precedente = $this->precedenteComplete();

        if ($this->estEnCours()) {
            $ecoule = $this->debut->diffInSeconds(CarbonImmutable::now());
            $fin = $precedente->debut->addSeconds((int) $ecoule);

            return new self($this->type, $precedente->debut, $fin->lessThan($precedente->fin) ? $fin : $precedente->fin);
        }

        return $precedente;
    }

    public function estEnCours(): bool
    {
        $maintenant = CarbonImmutable::now();

        return $this->debut->lessThanOrEqualTo($maintenant) && $this->fin->greaterThan($maintenant);
    }

    private function precedenteComplete(): self
    {
        return match ($this->type) {
            'jour' => new self($this->type, $this->debut->subDay(), $this->fin->subDay()),
            'semaine' => new self($this->type, $this->debut->subWeek(), $this->fin->subWeek()),
            'mois' => new self($this->type, $this->debut->subMonthNoOverflow()->startOfMonth(), $this->debut->subMonthNoOverflow()->endOfMonth()),
            default => new self($this->type, $this->debut->subDays($this->nombreJours()), $this->fin->subDays($this->nombreJours())),
        };
    }

    public function nombreJours(): int
    {
        return (int) $this->debut->diffInDays($this->fin->startOfDay()) + 1;
    }

    public function libelle(): string
    {
        return match ($this->type) {
            'jour' => "Aujourd'hui, ".$this->debut->translatedFormat('l j F Y'),
            'semaine' => 'Semaine du '.$this->debut->translatedFormat('j F').' au '.$this->fin->translatedFormat('j F Y'),
            'mois' => ucfirst($this->debut->translatedFormat('F Y')),
            default => $this->nombreJours() === 1
                ? ucfirst($this->debut->translatedFormat('l j F Y'))
                : 'Du '.$this->debut->format('d/m/Y').' au '.$this->fin->format('d/m/Y'),
        };
    }

    public function libellePrecedente(): string
    {
        $libelle = match ($this->type) {
            'jour' => 'hier',
            'semaine' => 'semaine dernière',
            'mois' => 'mois dernier',
            default => 'période précédente',
        };

        return $this->estEnCours() ? $libelle.' à la même heure' : $libelle;
    }
}
