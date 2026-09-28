<?php

use App\Http\Controllers\Api\PointageController;
use Illuminate\Support\Facades\Route;

// Appelé par le lecteur d'empreinte ou par l'agent local du poste d'accueil
Route::middleware(['lecteur', 'throttle:120,1'])->group(function () {
    Route::post('/pointage/empreinte', [PointageController::class, 'empreinte'])->name('api.pointage.empreinte');
});
