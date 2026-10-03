<?php

namespace App\Support;

class Badge
{
    /** Identifiant de badge accepté : chiffres, lettres, tiret, souligné (1 à 64 caractères). */
    public const REGLE = 'regex:/^[A-Za-z0-9_-]{1,64}$/';
}
