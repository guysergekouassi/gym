<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Réglages simples clé/valeur modifiables par l'admin (page Paramètres). */
class Parametre extends Model
{
    public const TARIF_JOURNALIER = 'tarif_journalier';

    /** Réglages de la salle qui remplacent les valeurs de config/salle.php. */
    public const SALLE = [
        'salle_nom' => 'nom',
        'salle_adresse' => 'adresse',
        'salle_telephone' => 'telephone',
        'salle_email' => 'email',
        'recu_message' => 'message_recu',
    ];

    private const CLE_CACHE = 'parametres.tous';

    protected $primaryKey = 'cle';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['cle', 'valeur'];

    public static function valeur(string $cle, mixed $defaut = null): mixed
    {
        return self::tous()[$cle] ?? $defaut;
    }

    public static function definir(string $cle, string|int|null $valeur): void
    {
        self::updateOrCreate(['cle' => $cle], ['valeur' => (string) $valeur]);
        Cache::forget(self::CLE_CACHE);
    }

    /** @return array<string, string> */
    public static function tous(): array
    {
        return Cache::rememberForever(self::CLE_CACHE, fn () => self::pluck('valeur', 'cle')->all());
    }

    /** Applique les réglages enregistrés à config('salle.*') (appelé au démarrage). */
    public static function appliquerALaConfig(): void
    {
        $valeurs = self::tous();

        foreach (self::SALLE as $cle => $config) {
            if (array_key_exists($cle, $valeurs)) {
                config(["salle.{$config}" => $valeurs[$cle]]);
            }
        }

        // Le nom de l'application est celui de la salle, partout (titres, PDF…)
        config(['app.name' => config('salle.nom')]);
    }

    /** Prix d'une entrée journalière : réglage admin, sinon valeur du .env. */
    public static function tarifJournalier(): int
    {
        return (int) self::valeur(self::TARIF_JOURNALIER, config('salle.tarif_journalier'));
    }
}
