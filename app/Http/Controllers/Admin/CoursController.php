<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coach;
use App\Models\Cours;
use App\Models\Salle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CoursController extends Controller
{
    public function index(): View
    {
        return view('admin.cours.index', ['cours' => Cours::with(['coach', 'salle'])->orderByDesc('actif')->orderBy('jour_semaine')->orderBy('heure')->get()]);
    }

    public function create(): View
    {
        return $this->formulaire(new Cours(['actif' => true, 'duree_minutes' => 60, 'capacite' => 20, 'jour_semaine' => 1, 'heure' => '18:30']));
    }

    public function store(Request $request): RedirectResponse
    {
        Cours::create($this->valider($request));

        return redirect()->route('admin.cours.index')->with('succes', 'Cours ajouté au planning.');
    }

    public function edit(Cours $cours): View
    {
        return $this->formulaire($cours);
    }

    public function update(Request $request, Cours $cours): RedirectResponse
    {
        $cours->update($this->valider($request));

        return redirect()->route('admin.cours.index')->with('succes', 'Cours mis à jour.');
    }

    private function formulaire(Cours $cours): View
    {
        return view('admin.cours.form', [
            'cours' => $cours,
            'coachs' => Coach::where('actif', true)->orderBy('nom')->get(),
            'salles' => Salle::where('actif', true)->orderBy('nom')->get(),
        ]);
    }

    private function valider(Request $request): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'coach_id' => ['nullable', 'integer', 'exists:coachs,id'],
            'salle_id' => ['nullable', 'integer', 'exists:salles,id'],
            'jour_semaine' => ['required', 'integer', Rule::in(array_keys(Cours::JOURS))],
            'heure' => ['required', 'date_format:H:i'],
            'duree_minutes' => ['required', 'integer', 'min:15', 'max:300'],
            'capacite' => ['required', 'integer', 'min:1', 'max:500'],
        ]) + ['actif' => $request->boolean('actif')];
    }
}