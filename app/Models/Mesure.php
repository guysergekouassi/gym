<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Mesure physique d'un membre (suivi de progression). */
class Mesure extends Model
{
    protected $fillable = ['client_id', 'date', 'poids', 'taille', 'tour_taille', 'masse_grasse', 'note', 'user_id'];

    protected function casts(): array
    {
        return ['date' => 'date', 'poids' => 'float', 'tour_taille' => 'float', 'masse_grasse' => 'float', 'taille' => 'integer'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function imc(): ?float
    {
        return $this->poids && $this->taille ? round($this->poids / (($this->taille / 100) ** 2), 1) : null;
    }
}