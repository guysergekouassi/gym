<?php

use App\Http\Controllers\AccueilController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CaisseController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RecuController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', fn () => redirect()->route(
        auth()->user()->isAdmin() ? 'dashboard' : 'caisse.index'
    ));

    Route::middleware('role:admin')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');
    });

    Route::middleware('role:admin,caissier')->group(function () {
        Route::get('/caisse', [CaisseController::class, 'index'])->name('caisse.index');
        Route::post('/caisse/journalier', [CaisseController::class, 'journalier'])->name('caisse.journalier');
        Route::post('/caisse/abonnement', [CaisseController::class, 'abonnement'])->name('caisse.abonnement');
        Route::get('/caisse/clients', [CaisseController::class, 'clients'])->name('caisse.clients');

        Route::get('/recus/{paiement}', [RecuController::class, 'show'])->name('recus.show');

        Route::resource('clients', ClientController::class)->except('destroy');

        Route::get('/accueil', [AccueilController::class, 'index'])->name('accueil.index');
        Route::get('/accueil/dernier', [AccueilController::class, 'dernier'])->name('accueil.dernier');
    });
});
