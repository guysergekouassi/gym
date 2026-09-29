<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Espace membre : le client connecté par son lien personnel est gardé en session. */
class MembreConnecte
{
    public function handle(Request $request, Closure $next): Response
    {
        $client = Client::find($request->session()->get('membre_id'));

        if (! $client) {
            $request->session()->forget('membre_id');

            return redirect()->route('membre.connexion');
        }

        $request->attributes->set('membre', $client);
        view()->share('membre', $client);

        return $next($request);
    }
}