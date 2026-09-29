<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\KpiService;
use Illuminate\View\View;

class RapportController extends Controller
{
    public function __invoke(KpiService $kpi): View
    {
        $mois = $kpi->revenusParMois(12);

        return view('admin.rapports', [
            'mois' => $mois,
            'prevu' => $kpi->revenuPrevu(),
            'cohortes' => $kpi->cohortes(6),
            'carte' => $kpi->carteAffluence(28),
            'formules' => $kpi->repartitionFormules(),
        ]);
    }
}