<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vente extends Model
{
    protected $fillable = [
        'numero', 'user_id', 'total', 'mode_paiement', 'montant_recu', 'monnaie_rendue',
        'annulee_at', 'annulee_par', 'motif_annulation',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'montant_recu' => 'integer',
            'monnaie_rendue' => 'integer',
            'annulee_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(VenteLigne::class);
    }

    public function annuleePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annulee_par');
    }

    /** Ventes qui comptent dans le chiffre d'affaires */
    public function scopeValides(Builder $query): Builder
    {
        return $query->whereNull('annulee_at');
    }

    public function estAnnulee(): bool
    {
        return $this->annulee_at !== null;
    }

    public function libelleModePaiement(): string
    {
        return config('salon.modes_paiement.'.$this->mode_paiement, $this->mode_paiement);
    }
}
