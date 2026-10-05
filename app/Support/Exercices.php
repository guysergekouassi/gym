<?php

namespace App\Support;

use App\Models\Paiement;

class Exercices
{
    /** Années proposées dans le filtre : de la première vente à l'année en cours. */
    public static function disponibles(): array
    {
        $premier = Paiement::min('created_at');
        $debut = $premier ? (int) substr((string) $premier, 0, 4) : (int) date('Y');

        return range((int) date('Y'), min($debut, (int) date('Y')));
    }
}
