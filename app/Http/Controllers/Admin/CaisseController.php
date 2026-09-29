<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Caisse;
use App\Models\Salle;
use App\Services\KpiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Gestion des caisses (points d'encaissement) par l'administrateur. */
class CaisseController extends Controller
{
    public function index(KpiService $kpi): View
    {
        $caisses = Caisse::with(['caissiers', 'salle'])->orderByDesc('actif')->orderBy('nom')->get();

        return view('admin.caisses.index', [
            'caisses' => $caisses,
            'resumes' => $caisses->mapWithKeys(fn (Caisse $c) => [$c->id => $kpi->resumeCaisse($c)]),
        ]);
    }

    public function create(): View
    {
        return view('admin.caisses.form', ['caisse' => new Caisse(['actif' => true]), 'salles' => Salle::where('actif', true)->orderBy('nom')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $caisse = Caisse::create($this->valider($request));

        return redirect()->route('admin.caisses.index')->with('succes', "Caisse « {$caisse->nom} » créée.");
    }

    public function show(Request $request, Caisse $caisse, KpiService $kpi): View
    {
        $request->validate(['date' => ['nullable', 'date', 'before_or_equal:today']]);
        $jour = $request->date('date') ?? today();

        return view('admin.caisses.show', [
            'caisse' => $caisse->load('caissiers'),
            'jour' => $jour,
            'resume' => $kpi->resumeCaisse($caisse, $jour),
        ]);
    }

    public function edit(Caisse $caisse): View
    {
        return view('admin.caisses.form', ['caisse' => $caisse, 'salles' => Salle::orderBy('nom')->get()]);
    }

    public function update(Request $request, Caisse $caisse): RedirectResponse
    {
        $caisse->update($this->valider($request, $caisse));

        return redirect()->route('admin.caisses.index')->with('succes', "Caisse « {$caisse->nom} » mise à jour.");
    }

    private function valider(Request $request, ?Caisse $caisse = null): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:100', Rule::unique('caisses', 'nom')->ignore($caisse?->id)],
            'emplacement' => ['nullable', 'string', 'max:150'],
            'salle_id' => ['nullable', 'integer', 'exists:salles,id'],
        ]) + ['actif' => $request->boolean('actif')];
    }
}
