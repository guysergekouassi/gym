<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as RegleMotDePasse;
use Illuminate\View\View;

/** Mot de passe oublié : lien de réinitialisation envoyé par e-mail. */
class MotDePasseController extends Controller
{
    public function demande(): View
    {
        return view('auth.mot-de-passe-oublie');
    }

    public function envoyer(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        // Même réponse que le compte existe ou non (on ne révèle pas les adresses)
        return back()->with('succes', "Si un compte existe pour cette adresse, un lien de réinitialisation vient d'être envoyé.");
    }

    public function formulaire(Request $request, string $token): View
    {
        return view('auth.reinitialiser', ['token' => $token, 'email' => $request->string('email')->value()]);
    }

    public function reinitialiser(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', RegleMotDePasse::min(8)],
        ]);

        $statut = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });

        return $statut === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('succes', 'Mot de passe modifié. Vous pouvez vous connecter.')
            : back()->withErrors(['email' => 'Ce lien n’est plus valable. Refaites une demande.'])->onlyInput('email');
    }
}