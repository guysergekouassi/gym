<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Formulaires ouverts dans une fenêtre (en-tête X-Modal) : la redirection de fin d'envoi
 * devient une réponse JSON { redirect }. La fenêtre se ferme et le navigateur va à cette adresse,
 * où le message de succès (flash) est encore disponible.
 */
class ReponseModale
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->headers->has('X-Modal') && $response instanceof RedirectResponse) {
            return response()->json(['redirect' => $response->getTargetUrl()]);
        }

        return $response;
    }
}
