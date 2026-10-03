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
            'avant' => $kpi->chiffresPeriode($periode->precedente()),
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
