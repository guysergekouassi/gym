<?php

use App\Http\Middleware\AuthentifierLecteur;
use App\Http\Middleware\MembreConnecte;
use App\Http\Middleware\ReponseModale;
use App\Http\Middleware\VerifierCaisse;
use App\Http\Middleware\VerifierRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => VerifierRole::class,
            'lecteur' => AuthentifierLecteur::class,
            'caisse' => VerifierCaisse::class,
            'membre' => MembreConnecte::class,
        ]);

        // Notification serveur à serveur de l'agrégateur de paiement (vérifiée par un appel retour)
        $middleware->validateCsrfTokens(except: ['paiement/notification']);

        $middleware->web(append: [ReponseModale::class]);

        $middleware->redirectGuestsTo('/login');
        // Déjà connecté : « / » renvoie vers le tableau de bord (admin) ou la caisse (caissière)
        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
