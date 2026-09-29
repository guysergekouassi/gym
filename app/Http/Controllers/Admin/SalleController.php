<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Salle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Plusieurs salles : chaque caisse, lecteur et cours appartient à une salle. */
class SalleController extends Controller
{
    public function index(): View
    {
        return view('admin.salles.index', [
            'salles' => Salle::withCount(['caisses', 'lecteurs'])->orderByDesc('actif')->orderBy('nom')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.salles.form', ['salle' => new Salle(['actif' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Salle::create($this->valider($request));

        return redirect()->route('admin.salles.index')->with('succes', 'Salle ajoutée.');
    }

    public function edit(Salle $salle): View
    {
        return view('admin.salles.form', ['salle' => $salle]);
    }

    public function update(Request $request, Salle $salle): RedirectResponse
    {
        $salle->update($this->valider($request, $salle));

        return redirect()->route('admin.salles.index')->with('succes', 'Salle mise à jour.');
    }

    private function valider(Request $request, ?Salle $salle = null): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:100', Rule::unique('salles', 'nom')->ignore($salle?->id)],
            'adresse' => ['nullable', 'string', 'max:200'],
            'telephone' => ['nullable', 'string', 'max:30'],
        ]) + ['actif' => $request->boolean('actif')];
    }
}