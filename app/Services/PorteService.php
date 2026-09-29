<?php

namespace App\Services;

use App\Models\Passage;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Ouvre la porte ou le tourniquet après un accès autorisé (relais réseau). */
class PorteService
{
    public function estActive(): bool
    {
        return config('salle.porte.driver') === 'http' && config('salle.porte.url');
    }

    public function ouvrir(Passage $passage): bool
    {
        if (! $passage->estAutorise() || ! $this->estActive()) {
            return false;
        }

        try {
            Http::timeout(3)->get(config('salle.porte.url'))->throw();

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
