<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CaisseController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\CompteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MesKpiController;
use App\Http\Controllers\MotDePasseController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', fn () => redirect(auth()->user()->pageAccueil()));

    Route::middleware('role:gerante,assistante')->group(function () {
        Route::get('/caisse', [CaisseController::class, 'index'])->name('caisse.index');
        Route::post('/caisse', [CaisseController::class, 'store'])->name('caisse.store');

        Route::get('/tickets/{vente}', [TicketController::class, 'show'])->name('tickets.show');
        Route::post('/tickets/{vente}/imprimer', [TicketController::class, 'imprimer'])->name('tickets.imprimer');

        Route::get('/mot-de-passe', [MotDePasseController::class, 'edit'])->name('mot-de-passe.edit');
        Route::put('/mot-de-passe', [MotDePasseController::class, 'update'])->name('mot-de-passe.update');
    });

    Route::middleware('role:assistante')->group(function () {
        Route::get('/mes-kpi', MesKpiController::class)->name('mes-kpi');
    });

    Route::middleware('role:gerante')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/ventes', [DashboardController::class, 'ventes'])->name('ventes.index');
        Route::post('/ventes/{vente}/annuler', [DashboardController::class, 'annuler'])->name('ventes.annuler');

        Route::resource('services', ServiceController::class)->except('show');
        Route::post('/categories', [CategorieController::class, 'store'])->name('categories.store');
        Route::put('/categories/{categorie}', [CategorieController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{categorie}', [CategorieController::class, 'destroy'])->name('categories.destroy');

        Route::get('/comptes', [CompteController::class, 'index'])->name('comptes.index');
        Route::post('/comptes', [CompteController::class, 'store'])->name('comptes.store');
        Route::patch('/comptes/{user}/activation', [CompteController::class, 'activation'])->name('comptes.activation');
        Route::put('/comptes/{user}/mot-de-passe', [CompteController::class, 'motDePasse'])->name('comptes.mot-de-passe');
    });
});
