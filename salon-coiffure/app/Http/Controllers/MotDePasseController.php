<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class MotDePasseController extends Controller
{
    public function edit(): View
    {
        return view('mot-de-passe');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'actuel' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ], ['actuel.current_password' => 'Le mot de passe actuel est incorrect.']);

        $request->user()->update(['password' => $data['password']]);

        return back()->with('succes', 'Mot de passe modifié.');
    }
}
