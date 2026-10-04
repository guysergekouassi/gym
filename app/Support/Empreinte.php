<?php

namespace App\Support;

use App\Models\Client;

class Empreinte
{
    /** N° d'utilisateur de la pointeuse : chiffres uniquement (1 à 9 chiffres, sans zéro initial). */
    public const REGLE = 'regex:/^[1-9][0-9]{0,8}$/';

    /**
     * N° réservés au personnel enregistré à la main sur la pointeuse (900000000 et plus) :
     * ils ouvrent le menu de l'appareil mais ne sont ni des clients ni comptés comme passages.
     */
    public const PERSONNEL_MIN = 900000000;

    /** N° d'un client : même règle, sans la plage du personnel. */
    public const REGLE_CLIENT = 'regex:/^(?!9[0-9]{8}$)[1-9][0-9]{0,8}$/';

    public static function estPersonnel(?string $numero): bool
    {
        return $numero !== null && ctype_digit($numero) && (int) $numero >= self::PERSONNEL_MIN;
    }

    /** Premier numéro libre, proposé à la création d'une fiche. */
    public static function prochainNumero(): int
    {
        $max = Client::withTrashed()->whereNotNull('empreinte_id')->pluck('empreinte_id')
            ->filter(fn ($v) => ctype_digit((string) $v))
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v < self::PERSONNEL_MIN)
            ->max();

        return ($max ?? 0) + 1;
    }
}
