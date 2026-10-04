<?php

namespace App\Http\Controllers;

use App\Models\Paiement;
use App\Services\KpiService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Tableau de bord de la caissière : sa journée de caisse. */
class JourneeController extends Controller
{
    public function __invoke(Request $request, KpiService $kpi): View
    {
        $caissier = $request->user();

        return view('journee', [
            'aujourdhui' => $kpi->chiffresCaisse(today(), $caissier),
            // Hier jusqu'à la même heure : comparaison juste en cours de journée
            'hier' => $kpi->chiffresCaisse(today()->subDay(), $caissier, now()->subDay()),
            'recettes' => $kpi->recettesParJour(7, $caissier),
            'transactions' => Paiement::with(['client', 'abonnement.formule'])
                ->where('user_id', $caissier->id)
                ->whereDate('created_at', today()->toDateString())
                ->latest('id')->limit(8)->get(),
        ]);
    }
}
