<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Produit;
use App\Models\Prospect;
use App\Models\Salle;
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
            'salle' => ['nullable', 'integer', 'exists:salles,id'],
        ]);

        $du = $request->date('du') ?? today()->subDays(30);
        $au = $request->date('au') ?? today();
        $salleId = $request->integer('salle') ?: null;

        return view('dashboard', [
            'du' => $du,
            'au' => $au,
            'salles' => Salle::where('actif', true)->orderBy('nom')->get(),
            'salleId' => $salleId,
            'jour' => $kpi->resumeJour($salleId),
            'abonnementsEnCours' => $kpi->nombreAbonnementsEnCours(),
            'actifs' => $kpi->abonnesActifs(),
            'moinsActifs' => $kpi->abonnesMoinsActifs(),
            'renouvellement' => $kpi->renouvellement($du, $au),
            'renouvellements' => $kpi->clientsQuiRenouvellent($du, $au),
            'expirantBientot' => $kpi->expirantBientot(),
            'derniersPassages' => $kpi->derniersPassages(),
            'formules' => $kpi->repartitionFormules(),
            'alertesStock' => Produit::enAlerte()->orderBy('stock')->get(),
            'messagesEnAttente' => Message::aEnvoyer()->count(),
            'prospectsOuverts' => Prospect::whereIn('statut', ['nouveau', 'essai_prevu', 'essai_fait'])->count(),
            'prevu' => $kpi->revenuPrevu(),
        ]);
    }
}
