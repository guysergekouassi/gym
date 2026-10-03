<?php

use App\Http\Controllers\Api\PointageController;
use Illuminate\Support\Facades\Route;

// API générique avec token (agent local, autre appareil). La pointeuse ZKTeco,
// elle, passe par le protocole Cloud/ADMS : voir routes/pointeuse.php
Route::middleware(['throttle:120,1', 'lecteur'])->group(function () {
    Route::post('/pointage/empreinte', [PointageController::class, 'empreinte'])->name('api.pointage.empreinte');
});
