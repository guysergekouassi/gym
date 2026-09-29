<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Clôture journalière d'une caisse : comptage du tiroir et écart. */
class Cloture extends Model
{
    public const COUPURES = [10000, 5000, 2000, 1000, 500, 250, 200, 100, 50, 25, 10, 5];

    protected $fillable = [
        'caisse_id', 'user_id', 'jour', 'fond_caisse', 'especes_encaissees',
        'especes_comptees', 'ecart', 'electronique', 'coupures', 'motif_ecart',
    ];

    protected function casts(): array
    {
        return [
            'jour' => 'date',
            'coupures' => 'array',
            'fond_caisse' => 'integer',
            'especes_encaissees' => 'integer',
            'especes_comptees' => 'integer',
            'ecart' => 'integer',
            'electronique' => 'integer',
        ];
    }

    public function caisse(): BelongsTo
    {
        return $this->belongsTo(Caisse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}