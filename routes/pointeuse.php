<?php

use App\Http\Controllers\PointeuseController;
use Illuminate\Support\Facades\Route;

// Adresses appelées par la pointeuse ZKTeco (protocole Cloud/ADMS).
// Le suffixe .aspx est utilisé par certains firmwares.
foreach (['', '.aspx'] as $suffixe) {
    Route::get("/iclock/cdata{$suffixe}", [PointeuseController::class, 'configuration']);
    Route::post("/iclock/cdata{$suffixe}", [PointeuseController::class, 'recevoir']);
    Route::get("/iclock/getrequest{$suffixe}", [PointeuseController::class, 'commandes']);
    Route::post("/iclock/devicecmd{$suffixe}", [PointeuseController::class, 'resultats']);
}
Route::get('/iclock/ping', fn () => response('OK', 200, ['Content-Type' => 'text/plain']));
