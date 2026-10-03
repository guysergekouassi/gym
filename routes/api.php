<?php

use App\Http\Controllers\Api\PointageController;
use Illuminate\Support\Facades\Route;

// Appelé par le boîtier de badge en réseau (ou un agent local) avec son token
Route::middleware(['throttle:120,1', 'lecteur'])->group(function () {
    Route::post('/pointage/badge', [PointageController::class, 'badge'])->name('api.pointage.badge');
    // Ancienne URL, conservée pour les appareils déjà configurés
    Route::post('/pointage/empreinte', [PointageController::class, 'badge']);
});
