<?php

use App\Http\Controllers\AccueilController;
use App\Http\Controllers\Admin\FormuleController;
use App\Http\Controllers\Admin\PointeuseController;
use App\Http\Controllers\Admin\PaiementController;
use App\Http\Controllers\Admin\ParametreController;
use App\Http\Controllers\Admin\UtilisateurController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MotDePasseController;
use App\Http\Controllers\CaisseController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JourneeController;
use App\Http\Controllers\RecuController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:20,1');
});

Route::middleware(['auth', 'mdp.change'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/mot-de-passe', [MotDePasseController::class, 'edit'])->name('mot-de-passe.edit');
    Route::put('/mot-de-passe', [MotDePasseController::class, 'update'])->middleware('throttle:10,1')->name('mot-de-passe.update');

    Route::get('/', fn () => redirect()->route(
        auth()->user()->isAdmin() ? 'dashboard' : 'journee'
    ));

    // --- Responsable (admin) uniquement ---
    Route::middleware('role:admin')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');

        Route::prefix('admin')->name('admin.')->group(function () {
            Route::get('/paiements', [PaiementController::class, 'index'])->name('paiements.index');
            Route::get('/paiements/export', [PaiementController::class, 'export'])->name('paiements.export');
            Route::post('/paiements/{paiement}/annuler', [PaiementController::class, 'annuler'])->name('paiements.annuler');

            Route::get('/formules', [FormuleController::class, 'index'])->name('formules.index');
            Route::post('/formules', [FormuleController::class, 'store'])->name('formules.store');
            Route::put('/formules/{formule}', [FormuleController::class, 'update'])->name('formules.update');
            Route::put('/tarif-journalier', [FormuleController::class, 'tarifJournalier'])->name('formules.tarif');

            Route::resource('utilisateurs', UtilisateurController::class)
                ->parameters(['utilisateurs' => 'utilisateur'])
                ->except(['show', 'destroy']);

            Route::get('/parametres', [ParametreController::class, 'edit'])->name('parametres.edit');
            Route::put('/parametres', [ParametreController::class, 'update'])->name('parametres.update');

            Route::get('/pointeuses', [PointeuseController::class, 'index'])->name('pointeuses.index');
            Route::post('/pointeuses', [PointeuseController::class, 'store'])->name('pointeuses.store');
            Route::post('/pointeuses/{lecteur}/synchroniser', [PointeuseController::class, 'synchroniser'])->name('pointeuses.synchroniser');
            Route::post('/pointeuses/{lecteur}/reinitialiser-ip', [PointeuseController::class, 'reinitialiserIp'])->name('pointeuses.ip');
            Route::delete('/pointeuses/{lecteur}', [PointeuseController::class, 'destroy'])->name('pointeuses.destroy');
        });
    });

    // --- Caissière et responsable ---
    Route::middleware('role:admin,caissier')->group(function () {
        Route::get('/ma-journee', JourneeController::class)->name('journee');
        Route::get('/caisse', [CaisseController::class, 'index'])->name('caisse.index');
        Route::post('/caisse/journalier', [CaisseController::class, 'journalier'])->middleware('throttle:30,1')->name('caisse.journalier');
        Route::post('/caisse/abonnement', [CaisseController::class, 'abonnement'])->middleware('throttle:30,1')->name('caisse.abonnement');
        Route::get('/caisse/clients', [CaisseController::class, 'clients'])->middleware('throttle:120,1')->name('caisse.clients');

        Route::get('/recus/{paiement}', [RecuController::class, 'show'])->name('recus.show');

        Route::resource('clients', ClientController::class)->except('destroy');

        Route::get('/accueil', [AccueilController::class, 'index'])->name('accueil.index');
        Route::get('/accueil/dernier', [AccueilController::class, 'dernier'])->name('accueil.dernier');
        Route::post('/accueil/scan', [AccueilController::class, 'scan'])->middleware('throttle:120,1')->name('accueil.scan');
    });
});
