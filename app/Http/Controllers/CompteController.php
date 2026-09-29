<?php

namespace App\Http\Controllers;

use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** « Mon compte » : nom, e-mail et mot de passe de la personne connectée. */
class CompteController extends Controller
{
    public function edit(): View
    {
        return view('compte.edit');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'mot_de_passe_actuel' => ['required', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ], [
            'mot_de_passe_actuel.current_password' => 'Le mot de passe actuel est incorrect.',
        ]);

        $changement = ! empty($data['password']);
        $user->update(array_filter([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'] ?? null,
        ]));

        if ($changement) {
            Journal::noter('compte.mot_de_passe', "{$user->name} a changé son mot de passe", $user);
        }

        return back()->with('succes', $changement ? 'Compte mis à jour, nouveau mot de passe enregistré.' : 'Compte mis à jour.');
    }
}