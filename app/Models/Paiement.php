<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Paiement extends Model
{
    public const TYPE_JOURNALIER = 'journalier';
    public const TYPE_ABONNEMENT = 'abonnement';

    public const TYPES = [
        self::TYPE_JOURNALIER => 'Entrée journalière',
        self::TYPE_ABONNEMENT => 'Abonnement',
    ];

    public const MODES = [
        'especes' => 'Espèces',
        'orange_money' => 'Orange Money',
        'mtn_momo' => 'MTN MoMo',
        'moov_money' => 'Moov Money',
        'wave' => 'Wave',
        'carte' => 'Carte bancaire',
    ];

    protected $fillable = [
        'numero_recu', 'client_id', 'abonnement_id', 'user_id',
        'type', 'montant', 'quantite', 'mode', 'reference',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'quantite' => 'integer',
            'annule_le' => 'datetime',
        ];
    }

    /** Encaissements non annulés : seuls ceux-là comptent dans la recette. */
    public function scopeValides(Builder $query): void
    {
        $query->whereNull('annule_le');
    }

    public function estAnnule(): bool
    {
        return $this->annule_le !== null;
    }

    public function annulePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annule_par');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function abonnement(): BelongsTo
    {
        return $this->belongsTo(Abonnement::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getRouteKeyName(): string
    {
        return 'numero_recu';
    }
}
