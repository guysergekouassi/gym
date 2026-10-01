<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerifierRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Compte désactivé par la gérante pendant que l'assistante était connectée : on la déconnecte
        if ($user && ! $user->actif) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['email' => 'Ce compte a été désactivé.']);
        }

        abort_unless($user && in_array($user->role, $roles, true), 403, 'Accès non autorisé.');

        return $next($request);
    }
}
