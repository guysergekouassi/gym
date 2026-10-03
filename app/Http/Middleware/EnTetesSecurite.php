<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes HTTP de durcissement : CSP stricte (aucun script inline ni tiers),
 * anti-clickjacking, anti-sniffing, pas de cache des pages authentifiées.
 */
class EnTetesSecurite
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $sources = "'self'";
        $connect = "'self'";
        if (Vite::isRunningHot()) {
            // Serveur de dev Vite (npm run dev) uniquement en local
            $hot = rtrim((string) file_get_contents(public_path('hot')));
            $sources .= ' '.$hot;
            $connect .= ' '.$hot.' '.preg_replace('#^http#', 'ws', $hot);
        }

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src {$sources}",
            "style-src {$sources} 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src {$connect}",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);

        $entetes = [
            'Content-Security-Policy' => $csp,
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        if ($request->isSecure()) {
            $entetes['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        // Poste de caisse partagé : après déconnexion, le bouton "Précédent" ne doit rien réafficher
        if ($request->user()) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        foreach ($entetes as $nom => $valeur) {
            if (! $response->headers->has($nom)) {
                $response->headers->set($nom, $valeur);
            }
        }

        // Ne pas révéler la version de PHP (à compléter par expose_php=Off dans php.ini)
        $response->headers->remove('X-Powered-By');
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}
