<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\KpiService;
use App\Support\Exercices;
use App\Support\Periode;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, KpiService $kpi): View
    {
        $periode = Periode::depuisRequete($request);
        $reference = $periode->dateReference();

        return view('dashboard', [
            'periode' => $periode,
            'annees' => Exercices::disponibles(),
            'chiffres' => $kpi->chiffresPeriode($periode),
            // Période en cours : comparée à la précédente au même moment (pas à la journée entière)
            'avant' => $kpi->chiffresPeriode($periode->precedente(), $periode->instantComparable()),
            // Jour ou semaine affiché : on montre aussi le cumul du mois (inutile si la période est déjà le mois)
            'caMois' => in_array($periode->granularite, ['jour', 'semaine'], true) ? $kpi->chiffreAffairesMois($periode) : null,
            'clientsTotal' => Client::count(),
            'serie' => $kpi->serieRecettes($periode),
            'repartition' => $kpi->repartitionClients($reference),
            'topFormules' => $kpi->topFormules(5, $reference),
            'activite' => $kpi->activiteRecente(),
            'derniersClients' => Client::latest('id')
                ->withMax(['abonnements as fin_droits' => fn ($q) => $q->where('statut', 'actif')], 'date_fin')
                ->limit(5)->get(),
            'expirantBientot' => $kpi->expirantBientot(),
        ]);
    }
}
