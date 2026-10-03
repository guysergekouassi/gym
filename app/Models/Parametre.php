<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Réglages simples clé/valeur modifiables par l'admin. */
class Parametre extends Model
{
    public const TARIF_JOURNALIER = 'tarif_journalier';

    protected $primaryKey = 'cle';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['cle', 'valeur'];

    public static function valeur(string $cle, mixed $defaut = null): mixed
    {
        return self::find($cle)?->valeur ?? $defaut;
    }

    public static function definir(string $cle, string|int $valeur): void
    {
        self::updateOrCreate(['cle' => $cle], ['valeur' => (string) $valeur]);
    }

    /** Prix d'une entrée journalière : réglage admin, sinon valeur du .env. */
    public static function tarifJournalier(): int
    {
        return (int) self::valeur(self::TARIF_JOURNALIER, config('salle.tarif_journalier'));
    }
}
