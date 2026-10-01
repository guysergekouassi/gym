<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CompteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class CompteController extends Controller
{
    public function index(CompteService $comptes): View
    {
        return view('comptes.index', [
            'assistantes' => User::assistantes()->withCount('ventes')->orderByDesc('actif')->orderBy('name')->get(),
            'actives' => $comptes->nombreActives(),
            'maximum' => $comptes->maximum(),
            'peutAjouter' => $comptes->peutAjouter(),
        ]);
    }

    public function store(Request $request, CompteService $comptes): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        $assistante = $comptes->creerAssistante($data);

        return back()->with('succes', "Compte de {$assistante->name} créé. Elle peut se connecter avec {$assistante->email}.");
    }

    public function activation(User $user, CompteService $comptes): RedirectResponse
    {
        abort_if($user->isGerante(), 403);

        $comptes->basculerActivation($user);

        return back()->with('succes', $user->actif ? "Compte de {$user->name} réactivé." : "Compte de {$user->name} désactivé : elle ne peut plus se connecter.");
    }

    public function motDePasse(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isGerante(), 403);

        $data = $request->validate([
            'password' => ['required', Password::min(6)],
        ]);

        $user->update(['password' => $data['password']]);

        return back()->with('succes', "Nouveau mot de passe enregistré pour {$user->name}.");
    }
}
