<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\KpiService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, KpiService $kpi): View
    {
        $request->validate([
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date', 'after_or_equal:du'],
        ]);

        $du = $request->date('du') ?? today()->subDays(30);
        $au = $request->date('au') ?? today();

        $debutMois = today()->startOfMonth();

        return view('dashboard', [
            'du' => $du,
            'au' => $au,
            'jour' => $kpi->resumeJour(),
            'aujourdhui' => $kpi->chiffresCaisse(today()),
            'hier' => $kpi->chiffresCaisse(today()->subDay()),
            'clientsTotal' => Client::count(),
            'clientsCeMois' => Client::where('created_at', '>=', $debutMois)->count(),
            'clientsMoisDernier' => Client::whereBetween('created_at', [$debutMois->copy()->subMonth(), $debutMois->copy()->subSecond()])->count(),
            'abonnementsEnCours' => $kpi->nombreAbonnementsEnCours(),
            'actifs' => $kpi->abonnesActifs(),
            'moinsActifs' => $kpi->abonnesMoinsActifs(),
            'renouvellement' => $kpi->renouvellement($du, $au),
            'renouvellements' => $kpi->clientsQuiRenouvellent($du, $au),
            'expirantBientot' => $kpi->expirantBientot(),
            'recettes' => $kpi->recettesParJour(7),
            'repartition' => $kpi->repartitionClients(),
            'topFormules' => $kpi->topFormules(),
            'activite' => $kpi->activiteRecente(),
            'derniersClients' => Client::latest('id')->limit(5)->get(),
        ]);
    }
}
