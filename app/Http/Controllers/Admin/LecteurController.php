<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lecteur;
use App\Models\Salle;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Lecteurs d'empreinte ou de badge : création du token et rattachement à une salle. */
class LecteurController extends Controller
{
    public function index(): View
    {
        return view('admin.lecteurs.index', [
            'lecteurs' => Lecteur::with('salle')->orderByDesc('actif')->orderBy('nom')->get(),
            'salles' => Salle::where('actif', true)->orderBy('nom')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'salle_id' => ['nullable', 'integer', 'exists:salles,id'],
        ]);

        [$lecteur, $token] = Lecteur::creerAvecToken($data['nom'], $data['salle_id'] ?? null);
        Journal::noter('lecteur.cree', "Lecteur « {$lecteur->nom} » créé", $lecteur);

        return back()->with('token_lecteur', ['nom' => $lecteur->nom, 'token' => $token]);
    }

    public function update(Request $request, Lecteur $lecteur): RedirectResponse
    {
        $lecteur->update($request->validate([
            'salle_id' => ['nullable', 'integer', 'exists:salles,id'],
        ]) + ['actif' => $request->boolean('actif')]);

        return back()->with('succes', "Lecteur « {$lecteur->nom} » mis à jour.");
    }
}