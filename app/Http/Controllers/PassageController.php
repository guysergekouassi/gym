<?php

namespace App\Http\Controllers;

use App\Models\Abonnement;
use App\Models\Passage;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Liste des clients qui ont pointé un jour donné. */
class PassageController extends Controller
{
    public const FILTRES = [
        'tous' => 'Tous',
        'abonne' => 'Abonnés',
        'journalier' => 'Journaliers',
        'refuse' => 'Refusés',
    ];

    public function __invoke(Request $request): View
    {
        $request->validate([
            'date' => ['nullable', 'date', 'before_or_equal:today'],
            'filtre' => ['nullable', 'in:'.implode(',', array_keys(self::FILTRES))],
            'vue' => ['nullable', 'in:passage,client'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $jour = $request->date('date') ?? today();
        $filtre = $request->input('filtre', 'tous');
        $vue = $request->input('vue', 'passage');
        $q = trim((string) $request->input('q', ''));

        $passagesJour = Passage::with(['client', 'user:id,name', 'lecteur:id,nom'])
            ->whereDate('passe_le', $jour->toDateString())
            ->latest('passe_le')
            ->latest('id')
            ->get();

        $autorises = $passagesJour->where('statut', Passage::STATUT_AUTORISE);

        $stats = [
            'entrees' => $autorises->count(),
            'distincts' => $autorises->whereNotNull('client_id')->unique('client_id')->count()
                + $autorises->whereNull('client_id')->count(),
            'journaliers' => $autorises->where('methode', Passage::METHODE_CAISSE)->count(),
            'refus' => $passagesJour->count() - $autorises->count(),
        ];

        $passages = $passagesJour->filter(fn (Passage $p) => $this->correspond($p, $filtre, $q))->values();

        return view('passages.index', [
            'jour' => $jour,
            'filtre' => $filtre,
            'vue' => $vue,
            'q' => $q,
            'stats' => $stats,
            'passages' => $passages,
            'parClient' => $vue === 'client' ? $this->regrouperParClient($passages) : collect(),
            'venues7j' => $this->venuesSurSeptJours($passages->pluck('client_id')->filter()->unique(), $jour),
            'finDroits' => $this->finDesDroits($passages->pluck('client_id')->filter()->unique()),
        ]);
    }

    /** Fin des droits de chaque client (renouvellements anticipés compris), en une requête. */
    private function finDesDroits(Collection $clientIds): Collection
    {
        if ($clientIds->isEmpty()) {
            return collect();
        }

        return Abonnement::query()
            ->whereIn('client_id', $clientIds)
            ->where('statut', Abonnement::STATUT_ACTIF)
            ->whereDate('date_fin', '>=', today()->toDateString())
            ->groupBy('client_id')
            ->select('client_id', DB::raw('MAX(date_fin) as fin'))
            ->pluck('fin', 'client_id')
            ->map(fn ($fin) => Carbon::parse($fin));
    }

    private function correspond(Passage $passage, string $filtre, string $q): bool
    {
        $ok = match ($filtre) {
            'abonne' => $passage->estAutorise() && $passage->methode !== Passage::METHODE_CAISSE,
            'journalier' => $passage->estAutorise() && $passage->methode === Passage::METHODE_CAISSE,
            'refuse' => ! $passage->estAutorise(),
            default => true,
        };

        if (! $ok || $q === '') {
            return $ok;
        }

        return str_contains(mb_strtolower($passage->client?->nom_complet ?? ''), mb_strtolower($q))
            || str_contains((string) $passage->client?->telephone, $q)
            || (string) $passage->empreinte_id === $q;
    }

    /** Une ligne par client : heure d'arrivée, heure de sortie, nombre de passages et de refus. */
    private function regrouperParClient(Collection $passages): Collection
    {
        return $passages->reverse()
            ->groupBy(fn (Passage $p) => $p->client_id ? "c{$p->client_id}" : "p{$p->id}")
            ->map(fn (Collection $groupe) => [
                'passage' => $groupe->last(),
                'arrivee' => $groupe->first()->passe_le,
                'sortie'  => $groupe->filter->estAutorise()->sortBy('passe_le')->last()?->sorti_le,
                'duree'   => $groupe->filter->estAutorise()->sortBy('passe_le')->last()?->dureeMinutes(),
                'entrees' => $groupe->filter->estAutorise()->count(),
                'refus'   => $groupe->reject->estAutorise()->count(),
            ])
            ->sortBy('arrivee')
            ->values();
    }

    /** Nombre de jours de venue sur les 7 jours se terminant au jour affiché. */
    private function venuesSurSeptJours(Collection $clientIds, Carbon $jour): Collection
    {
        if ($clientIds->isEmpty()) {
            return collect();
        }

        return Passage::query()
            ->whereIn('client_id', $clientIds)
            ->where('statut', Passage::STATUT_AUTORISE)
            ->whereBetween('passe_le', [$jour->copy()->subDays(6)->startOfDay(), $jour->copy()->endOfDay()])
            ->groupBy('client_id')
            ->select('client_id', DB::raw('COUNT(DISTINCT DATE(passe_le)) as jours'))
            ->pluck('jours', 'client_id');
    }
}
