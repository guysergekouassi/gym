<?php

namespace App\Http\Middleware;

use App\Models\Lecteur;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Authentifie un lecteur d'empreinte (ou l'agent local) par son token. */
class AuthentifierLecteur
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?? $request->header('X-Lecteur-Token');
        $lecteur = Lecteur::trouverParToken($token);

        if (! $lecteur) {
            return response()->json(['message' => 'Lecteur non autorisé.'], 401);
        }

        $lecteur->forceFill(['derniere_activite_at' => now()])->save();
        $request->attributes->set('lecteur', $lecteur);

        return $next($request);
    }
}
