<?php

namespace App\Support;

class Fcfa
{
    public static function format(int|float|null $montant): string
    {
        return number_format((int) $montant, 0, ',', ' ').' FCFA';
    }
}
