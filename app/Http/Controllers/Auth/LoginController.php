<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    private const ESSAIS_MAX = 5;

    private const BLOCAGE_SECONDES = 300;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $identifiants = $request->validate([
            'email' => ['required', 'string', 'email', 'max:150'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        // Verrouillage par compte + adresse IP : 5 essais puis 5 minutes d'attente
        $cle = 'login:'.Str::transliterate(Str::lower($identifiants['email'])).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($cle, self::ESSAIS_MAX)) {
            $minutes = (int) ceil(RateLimiter::availableIn($cle) / 60);

            throw ValidationException::withMessages([
                'email' => "Trop de tentatives. Réessayez dans {$minutes} minute(s).",
            ]);
        }

        if (! Auth::attempt($identifiants + ['actif' => true], $request->boolean('remember'))) {
            RateLimiter::hit($cle, self::BLOCAGE_SECONDES);
            Log::warning('Échec de connexion', ['email' => $identifiants['email'], 'ip' => $request->ip()]);

            throw ValidationException::withMessages([
                'email' => 'Identifiants incorrects ou compte désactivé.',
            ]);
        }

        RateLimiter::clear($cle);
        $request->session()->regenerate();

        $user = $request->user();
        $user->forceFill(['derniere_connexion_at' => now()])->save();

        if ($user->doit_changer_mdp) {
            return redirect()->route('mot-de-passe.edit');
        }

        return redirect()->intended($user->isAdmin() ? route('dashboard') : route('journee'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('succes', 'Vous avez été déconnecté avec succès.');
    }
}
