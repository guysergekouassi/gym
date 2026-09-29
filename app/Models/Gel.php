<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Suspension d'un abonnement : les jours gelés sont rendus en fin d'abonnement. */
class Gel extends Model
{
    protected $fillable = ['abonnement_id', 'client_id', 'du', 'au', 'jours', 'motif', 'user_id'];

    protected function casts(): array
    {
        return ['du' => 'date', 'au' => 'date', 'jours' => 'integer'];
    }

    public function abonnement(): BelongsTo
    {
        return $this->belongsTo(Abonnement::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function estEnCours(): bool
    {
        return today()->betweenIncluded($this->du, $this->au);
    }
}