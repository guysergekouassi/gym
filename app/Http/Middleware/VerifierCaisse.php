<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** La caissière ne peut encaisser que si une caisse active lui est attribuée. */
class VerifierCaisse
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->caisseActive()) {
            if ($request->isMethod('GET') && ! $request->expectsJson()) {
                return response()->view('caisse.sans-caisse', status: 403);
            }

            abort(403, "Aucune caisse active ne vous est attribuée. Contactez l'administrateur.");
        }

        return $next($request);
    }
}
