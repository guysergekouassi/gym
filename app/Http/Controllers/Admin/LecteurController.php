<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lecteur;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Boîtiers de badge en réseau : chacun a son propre token API (révocable). */
class LecteurController extends Controller
{
    public function index(): View
    {
        return view('admin.lecteurs.index', [
            'lecteurs' => Lecteur::orderByDesc('actif')->orderBy('nom')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['nom' => ['required', 'string', 'max:100']]);

        [$lecteur, $token] = Lecteur::creerAvecToken($data['nom']);

        // Le token n'est affiché qu'une seule fois (seule son empreinte SHA-256 est stockée)
        return back()->with('token_lecteur', ['nom' => $lecteur->nom, 'token' => $token]);
    }

    public function destroy(Lecteur $lecteur): RedirectResponse
    {
        $lecteur->update(['actif' => false]);

        return back()->with('succes', "Lecteur « {$lecteur->nom} » révoqué : son token ne fonctionne plus.");
    }
}
