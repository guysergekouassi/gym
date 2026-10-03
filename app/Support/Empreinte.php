<?php

namespace App\Support;

use App\Models\Client;

class Empreinte
{
    /** N° d'utilisateur de la pointeuse : chiffres uniquement (1 à 9 chiffres, sans zéro initial). */
    public const REGLE = 'regex:/^[1-9][0-9]{0,8}$/';

    /** Premier numéro libre, proposé à la création d'une fiche. */
    public static function prochainNumero(): int
    {
        $max = Client::withTrashed()->whereNotNull('empreinte_id')->pluck('empreinte_id')
            ->filter(fn ($v) => ctype_digit((string) $v))
            ->map(fn ($v) => (int) $v)
            ->max();

        return ($max ?? 0) + 1;
    }
}
