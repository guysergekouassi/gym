<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Vente;
use App\Services\KpiService;
use App\Services\VenteService;
use App\Support\Periode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, KpiService $kpi): View
    {
        $periode = $this->periode($request);

        return view('dashboard', [
            'kpi' => $kpi->calculer($periode),
            'derniers' => Vente::with(['user', 'lignes'])
                ->whereBetween('created_at', [$periode->debut, $periode->fin])
                ->latest()->limit(8)->get(),
        ]);
    }

    public function ventes(Request $request): View
    {
        $periode = $this->periode($request);

        $ventes = Vente::with(['user', 'lignes'])
            ->whereBetween('created_at', [$periode->debut, $periode->fin])
            ->when($request->integer('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->input('statut') === 'annulees', fn ($q) => $q->whereNotNull('annulee_at'))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('ventes.index', [
            'periode' => $periode,
            'ventes' => $ventes,
            'personnes' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function annuler(Request $request, Vente $vente, VenteService $service): RedirectResponse
    {
        $data = $request->validate([
            'motif' => ['required', 'string', 'max:255'],
        ], ['motif.required' => 'Indiquez le motif de l\'annulation.']);

        $service->annuler($vente, $request->user(), $data['motif']);

        return back()->with('succes', "Ticket {$vente->numero} annulé : il ne compte plus dans les ventes.");
    }

    private function periode(Request $request): Periode
    {
        return Periode::depuis(
            (string) $request->input('periode', 'jour'),
            $request->input('du'),
            $request->input('au'),
        );
    }
}
