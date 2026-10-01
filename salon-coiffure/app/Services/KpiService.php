<?php

namespace App\Services;

use App\Models\Categorie;
use App\Models\User;
use App\Models\Vente;
use App\Support\Periode;
use Illuminate\Support\Collection;

class KpiService
{
    /**
     * Tous les indicateurs d'une période. Avec $user, seulement les ventes de cette personne.
     * Les calculs sont faits en PHP : le volume d'un salon reste faible et ça marche sur SQLite comme MySQL.
     */
    public function calculer(Periode $periode, ?User $user = null): array
    {
        $ventes = $this->ventes($periode, $user);
        $resume = $this->resume($ventes);
        $precedent = $this->resume($this->ventes($periode->precedente(), $user));

        $lignes = $ventes->flatMap->lignes;

        return [
            'periode' => $periode,
            'resume' => $resume,
            'precedent' => $precedent,
            'evolution' => [
                'ca' => $this->evolution($resume['ca'], $precedent['ca']),
                'tickets' => $this->evolution($resume['tickets'], $precedent['tickets']),
                'panier_moyen' => $this->evolution($resume['panier_moyen'], $precedent['panier_moyen']),
            ],
            'courbe' => $this->courbe($periode, $ventes),
            'top_services' => $lignes->groupBy('libelle')
                ->map(fn (Collection $l, string $libelle) => [
                    'libelle' => $libelle,
                    'quantite' => $l->sum('quantite'),
                    'ca' => $l->sum('total'),
                ])
                ->sortByDesc('ca')->values()->take(10)->all(),
            'categories' => $this->parCategorie($lignes, $resume['ca']),
            'heures' => $this->parHeure($ventes),
            'jours_semaine' => $this->parJourSemaine($ventes),
            'modes_paiement' => $ventes->groupBy('mode_paiement')
                ->map(fn (Collection $v, string $mode) => [
                    'libelle' => config('salon.modes_paiement.'.$mode, $mode),
                    'tickets' => $v->count(),
                    'ca' => $v->sum('total'),
                    'part' => $resume['ca'] > 0 ? round($v->sum('total') * 100 / $resume['ca']) : 0,
                ])
                ->sortByDesc('ca')->values()->all(),
            'par_personne' => $user ? [] : $ventes->groupBy('user_id')
                ->map(fn (Collection $v) => [
                    'nom' => $v->first()->user?->name ?? '—',
                    'role' => $v->first()->user?->role,
                    'tickets' => $v->count(),
                    'ca' => $v->sum('total'),
                    'panier_moyen' => (int) round($v->avg('total')),
                ])
                ->sortByDesc('ca')->values()->all(),
            'annulations' => $this->annulations($periode, $user),
        ];
    }

    private function ventes(Periode $periode, ?User $user): Collection
    {
        return Vente::valides()
            ->whereBetween('created_at', [$periode->debut, $periode->fin])
            ->when($user, fn ($q) => $q->where('user_id', $user->id))
            ->with(['lignes', 'user'])
            ->get();
    }

    private function resume(Collection $ventes): array
    {
        $ca = (int) $ventes->sum('total');
        $tickets = $ventes->count();

        return [
            'ca' => $ca,
            'tickets' => $tickets,
            'panier_moyen' => $tickets > 0 ? (int) round($ca / $tickets) : 0,
            'prestations' => (int) $ventes->flatMap->lignes->sum('quantite'),
        ];
    }

    /** Variation en % ; null quand il n'y a rien à comparer */
    private function evolution(int $actuel, int $precedent): ?float
    {
        if ($precedent === 0) {
            return null;
        }

        return round(($actuel - $precedent) * 100 / $precedent, 1);
    }

    /** CA heure par heure sur une journée, sinon jour par jour */
    private function courbe(Periode $periode, Collection $ventes): array
    {
        if ($periode->nombreJours() === 1) {
            $parHeure = $ventes->groupBy(fn (Vente $v) => (int) $v->created_at->format('G'));
            $heures = $ventes->isEmpty() ? range(8, 20) : range(min(8, $parHeure->keys()->min()), max(20, $parHeure->keys()->max()));

            return array_map(fn (int $h) => [
                'libelle' => $h.'h',
                'valeur' => (int) ($parHeure->get($h)?->sum('total') ?? 0),
            ], $heures);
        }

        $parJour = $ventes->groupBy(fn (Vente $v) => $v->created_at->format('Y-m-d'));
        $points = [];
        for ($jour = $periode->debut; $jour->lessThanOrEqualTo($periode->fin); $jour = $jour->addDay()) {
            $points[] = [
                'libelle' => $periode->nombreJours() <= 7 ? ucfirst($jour->translatedFormat('D j')) : $jour->format('d/m'),
                'valeur' => (int) ($parJour->get($jour->format('Y-m-d'))?->sum('total') ?? 0),
            ];
        }

        return $points;
    }

    private function parCategorie(Collection $lignes, int $ca): array
    {
        $categories = Categorie::pluck('couleur', 'id');
        $noms = Categorie::pluck('nom', 'id');

        return $lignes->groupBy('categorie_id')
            ->map(fn (Collection $l, $id) => [
                'libelle' => $noms[$id] ?? 'Sans catégorie',
                'couleur' => $categories[$id] ?? '#94a3b8',
                'ca' => $l->sum('total'),
                'part' => $ca > 0 ? round($l->sum('total') * 100 / $ca) : 0,
            ])
            ->sortByDesc('ca')->values()->all();
    }

    private function parHeure(Collection $ventes): array
    {
        $parHeure = $ventes->groupBy(fn (Vente $v) => (int) $v->created_at->format('G'));

        return collect(range(7, 21))
            ->map(fn (int $h) => ['libelle' => (string) $h, 'valeur' => $parHeure->get($h)?->count() ?? 0])
            ->all();
    }

    private function parJourSemaine(Collection $ventes): array
    {
        $jours = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 7 => 'Dim'];
        $parJour = $ventes->groupBy(fn (Vente $v) => $v->created_at->dayOfWeekIso);

        return collect($jours)
            ->map(fn (string $libelle, int $iso) => ['libelle' => $libelle, 'valeur' => (int) ($parJour->get($iso)?->sum('total') ?? 0)])
            ->values()->all();
    }

    private function annulations(Periode $periode, ?User $user): array
    {
        $annulees = Vente::whereNotNull('annulee_at')
            ->whereBetween('created_at', [$periode->debut, $periode->fin])
            ->when($user, fn ($q) => $q->where('user_id', $user->id));

        return ['nombre' => (clone $annulees)->count(), 'montant' => (int) $annulees->sum('total')];
    }
}
