<?php

namespace App\Http\Controllers;

use App\Models\Vente;
use App\Services\KpiService;
use App\Support\Periode;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** L'assistante ne voit que ses propres chiffres, du jour et de la semaine */
class MesKpiController extends Controller
{
    public function __invoke(Request $request, KpiService $kpi): View
    {
        $user = $request->user();

        return view('mes-kpi', [
            'jour' => $kpi->calculer(Periode::depuis('jour'), $user),
            'semaine' => $kpi->calculer(Periode::depuis('semaine'), $user),
            'tickets' => Vente::where('user_id', $user->id)
                ->whereDate('created_at', today())
                ->latest()->get(),
        ]);
    }
}
