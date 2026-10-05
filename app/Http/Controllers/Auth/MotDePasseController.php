<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Sessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MotDePasseController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.mot-de-passe', ['force' => $request->user()->doit_changer_mdp]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'max:200', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();

        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'Le nouveau mot de passe doit être différent de l\'actuel.']);
        }

        $user->forceFill([
            'password' => $data['password'],
            'doit_changer_mdp' => false,
        ])->save();

        // Les autres sessions ouvertes avec l'ancien mot de passe sont fermées
        Sessions::revoquer($user, $request->session()->getId());
        $request->session()->regenerate();
        Auth::setUser($user);

        return redirect('/')->with('succes', 'Mot de passe modifié.');
    }
}
