<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Sessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Comptes du personnel : caissières et responsables. */
class UtilisateurController extends Controller
{
    public function index(): View
    {
        return view('admin.utilisateurs.index', [
            'utilisateurs' => User::orderByDesc('actif')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.utilisateurs.form', ['utilisateur' => new User(['role' => User::ROLE_CAISSIER, 'actif' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => ['required', 'string', 'max:200', 'confirmed', Password::defaults()],
        ]);

        // Mot de passe provisoire : la personne devra le changer à sa première connexion
        $utilisateur = User::create($data + ['actif' => true, 'doit_changer_mdp' => true]);

        Log::notice('Compte créé', ['id' => $utilisateur->id, 'role' => $utilisateur->role, 'par' => $request->user()->id]);

        return redirect()->route('admin.utilisateurs.index')
            ->with('succes', "Compte de {$utilisateur->name} créé. Mot de passe à changer à la première connexion.");
    }

    public function edit(User $utilisateur): View
    {
        return view('admin.utilisateurs.form', ['utilisateur' => $utilisateur]);
    }

    /** Suppression d'un compte qui n'a jamais encaissé (sinon : désactiver, pour garder l'historique). */
    public function supprimer(Request $request, User $utilisateur): RedirectResponse
    {
        if ($utilisateur->is($request->user())) {
            return back()->with('erreur', 'Vous ne pouvez pas supprimer votre propre compte.');
        }
        if (\App\Models\Paiement::where('user_id', $utilisateur->id)->exists()) {
            return back()->with('erreur', "{$utilisateur->name} a déjà encaissé : désactivez le compte (Modifier) pour garder l'historique de caisse.");
        }

        Sessions::revoquer($utilisateur);
        $utilisateur->delete();
        Log::notice('Compte supprimé', ['id' => $utilisateur->id, 'par' => $request->user()->id]);

        return redirect()->route('admin.utilisateurs.index')->with('succes', "Compte de {$utilisateur->name} supprimé.");
    }

    public function update(Request $request, User $utilisateur): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($utilisateur->id)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => ['nullable', 'string', 'max:200', 'confirmed', Password::defaults()],
        ]);
        $data['actif'] = $request->boolean('actif');

        // Garde-fou : on ne peut pas se retirer soi-même l'accès admin, ni se désactiver
        if ($utilisateur->is($request->user()) && (! $data['actif'] || $data['role'] !== User::ROLE_ADMIN)) {
            return back()->with('erreur', 'Vous ne pouvez pas désactiver votre propre compte ni retirer votre rôle de responsable.');
        }

        $reinitialise = filled($data['password'] ?? null);
        if ($reinitialise) {
            $data['doit_changer_mdp'] = true;
        } else {
            unset($data['password']);
        }

        $utilisateur->update($data);
        $changements = array_keys($utilisateur->getChanges());

        // Compte désactivé, rôle changé ou mot de passe réinitialisé : déconnexion immédiate partout
        if ($reinitialise || ! $utilisateur->actif || $utilisateur->wasChanged('role')) {
            Sessions::revoquer($utilisateur);
        }

        Log::notice('Compte modifié', [
            'id' => $utilisateur->id,
            'changements' => array_values(array_diff($changements, ['password', 'remember_token', 'updated_at'])),
            'mot_de_passe_reinitialise' => $reinitialise,
            'par' => $request->user()->id,
        ]);

        return redirect()->route('admin.utilisateurs.index')->with('succes', "Compte de {$utilisateur->name} mis à jour.");
    }
}
