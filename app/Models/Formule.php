<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Formule extends Model
{
    public const TYPE_ABONNEMENT = 'abonnement';
    public const TYPE_CARNET = 'carnet';
    public const TYPE_COACHING = 'coaching';

    public const TYPES = [
        self::TYPE_ABONNEMENT => 'Abonnement (durée)',
        self::TYPE_CARNET => 'Carnet d’entrées',
        self::TYPE_COACHING => 'Pack coaching',
    ];

    public const CATEGORIES = ['Standard', 'Étudiant', 'Couple', 'Entreprise', 'Senior'];

    protected $fillable = ['nom', 'type', 'categorie', 'duree_jours', 'nb_entrees', 'nb_seances', 'prix', 'description', 'actif'];

    protected function casts(): array
    {
        return [
            'duree_jours' => 'integer',
            'nb_entrees' => 'integer',
            'nb_seances' => 'integer',
            'prix' => 'integer',
            'actif' => 'boolean',
        ];
    }

    public function abonnements(): HasMany
    {
        return $this->hasMany(Abonnement::class);
    }

    /** Formules qui donnent accès à la salle (abonnements et carnets). */
    public function scopeAcces(Builder $query): void
    {
        $query->whereIn('type', [self::TYPE_ABONNEMENT, self::TYPE_CARNET]);
    }

    public function estCarnet(): bool
    {
        return $this->type === self::TYPE_CARNET;
    }

    public function resume(): string
    {
        return match ($this->type) {
            // Espaces insécables : « 90 j » ne se coupe jamais en fin de ligne
            self::TYPE_CARNET => "{$this->nb_entrees}\u{00A0}entrées · valable {$this->duree_jours}\u{00A0}j",
            self::TYPE_COACHING => "{$this->nb_seances}\u{00A0}séances · valable {$this->duree_jours}\u{00A0}j",
            default => "{$this->duree_jours}\u{00A0}jours",
        };
    }
}
