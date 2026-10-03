<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Un compte créé ou réinitialisé par l'admin doit choisir son propre mot de passe. */
class ForcerChangementMotDePasse
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->doit_changer_mdp && ! $request->routeIs('mot-de-passe.*', 'logout')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Changement de mot de passe requis.'], 403);
            }

            return redirect()->route('mot-de-passe.edit')
                ->with('erreur', 'Pour votre sécurité, choisissez un nouveau mot de passe avant de continuer.');
        }

        return $next($request);
    }
}
