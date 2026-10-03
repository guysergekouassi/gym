<?php

use App\Http\Middleware\AuthentifierLecteur;
use App\Http\Middleware\EnTetesSecurite;
use App\Http\Middleware\ForcerChangementMotDePasse;
use App\Http\Middleware\VerifierRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Pointeuse ZKTeco : ni session ni CSRF (c'est un appareil), débit limité
            Route::middleware('throttle:600,1')->group(base_path('routes/pointeuse.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => VerifierRole::class,
            'lecteur' => AuthentifierLecteur::class,
            'mdp.change' => ForcerChangementMotDePasse::class,
        ]);

        $middleware->append(EnTetesSecurite::class);

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Jamais de mots de passe ou de tokens dans les logs / pages d'erreur
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'mot_de_passe']);
    })->create();
