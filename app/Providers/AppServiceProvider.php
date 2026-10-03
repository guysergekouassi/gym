<?php

namespace App\Providers;

use App\Services\KpiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Mots de passe : 10 caractères minimum, majuscules, minuscules et chiffres
        Password::defaults(fn () => Password::min(10)->letters()->mixedCase()->numbers());

        // Cloche de la barre du haut : abonnements qui expirent bientôt
        View::composer('layouts.app', function ($view) {
            if (auth()->check()) {
                $view->with('alertes', app(KpiService::class)->expirantBientot());
            }
        });

        if ($this->app->isProduction()) {
            // Interdit migrate:fresh / db:wipe sur la base de production
            DB::prohibitDestructiveCommands();

            if (str_starts_with((string) config('app.url'), 'https://')) {
                URL::forceHttps();
            }
        }
    }
}
