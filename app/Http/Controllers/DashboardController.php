<?php

namespace App\Http\Controllers;

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

        return view('dashboard', [
            'du' => $du,
            'au' => $au,
            'jour' => $kpi->resumeJour(),
            'abonnementsEnCours' => $kpi->nombreAbonnementsEnCours(),
            'actifs' => $kpi->abonnesActifs(),
            'moinsActifs' => $kpi->abonnesMoinsActifs(),
            'renouvellement' => $kpi->renouvellement($du, $au),
            'renouvellements' => $kpi->clientsQuiRenouvellent($du, $au),
            'expirantBientot' => $kpi->expirantBientot(),
        ]);
    }
}
