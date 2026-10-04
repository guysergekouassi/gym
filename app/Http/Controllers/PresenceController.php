<?php

namespace App\Http\Controllers;

use App\Models\Passage;
use App\Support\Exercices;
use App\Support\Horaires;
use App\Support\Periode;
use App\Support\Recherche;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Entrées / départs : une ligne par arrivée, avec l'heure de départ du même jour.
 * 1er badge du jour = arrivée, 2e = départ (voir PointageService::sens).
 */
class PresenceController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $periode = Periode::depuisRequete($request);
        $bornes = [$periode->du->startOfDay(), $periode->au->endOfDay()];
        $q = trim((string) $request->query('q'));

        $arrivees = $this->arrivees()
            ->whereBetween('passages.passe_le', $bornes)
            ->when($q !== '', fn (Builder $query) => $query->whereHas('client', fn (Builder $c) => Recherche::appliquer($c, $q, ['nom', 'prenoms', 'telephone', 'empreinte_id'])));

        $lignes = (clone $arrivees)->with(['client', 'lecteur:id,nom', 'user:id,name'])
            ->latest('passages.passe_le')->latest('passages.id')
            ->paginate(25)->withQueryString();

        // Durée moyenne : arrivées de la période qui ont un départ
        $durees = (clone $arrivees)->get(['passages.id', 'passages.passe_le'])
            ->filter(fn (Passage $p) => $p->depart_le)
            ->map(fn (Passage $p) => $p->passe_le->diffInSeconds($p->depart_le));

        $aujourdhui = [CarbonImmutable::today(), CarbonImmutable::today()->endOfDay()];
        // Après l'heure de fin des séances, plus personne n'est « présent » : départ non badgé
        $salleFermee = Horaires::estFermeA(CarbonImmutable::now());

        return view('presences.index', [
            'periode' => $periode,
            'annees' => Exercices::disponibles(),
            'lignes' => $lignes,
            'salleFermee' => $salleFermee,
            'finDuJour' => Horaires::finDuJour(CarbonImmutable::today()),
            'chiffres' => [
                'entrees' => Passage::where('sens', Passage::SENS_ENTREE)->whereBetween('passe_le', $bornes)->count(),
                'departs' => Passage::where('sens', Passage::SENS_DEPART)->whereBetween('passe_le', $bornes)->count(),
                'presents' => $salleFermee ? 0 : $this->arrivees()->whereNotNull('passages.client_id')
                    ->where('passages.methode', Passage::METHODE_EMPREINTE)
                    ->whereBetween('passages.passe_le', $aujourdhui)->get(['passages.id', 'passages.passe_le'])
                    ->whereNull('depart_le')->count(),
                'duree_moyenne' => $durees->isEmpty() ? null : (int) $durees->avg(),
                'refus' => Passage::where('statut', Passage::STATUT_REFUSE)->whereBetween('passe_le', $bornes)->count(),
            ],
        ]);
    }

    /** Arrivées, avec l'heure du départ du même client le même jour (sous-requête). */
    private function arrivees(): Builder
    {
        $depart = Passage::from('passages as d')
            ->select('d.passe_le')
            ->whereColumn('d.client_id', 'passages.client_id')
            ->where('d.sens', Passage::SENS_DEPART)
            ->whereRaw('date(d.passe_le) = date(passages.passe_le)')
            ->orderBy('d.passe_le')
            ->limit(1);

        return Passage::query()
            ->where('passages.sens', Passage::SENS_ENTREE)
            ->select('passages.*')
            ->addSelect(['depart_le' => $depart])
            ->withCasts(['depart_le' => 'datetime']);
    }

    /** « 1 h 25 min 12 s ». */
    public static function duree(?int $secondes): string
    {
        if ($secondes === null) {
            return '—';
        }

        $h = intdiv($secondes, 3600);
        $m = intdiv($secondes % 3600, 60);
        $s = $secondes % 60;

        return trim(($h ? "{$h} h " : '').($h || $m ? "{$m} min " : '')."{$s} s");
    }
}
