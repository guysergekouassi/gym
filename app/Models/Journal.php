<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Journal des actions sensibles : qui a fait quoi, quand. */
class Journal extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'journal';

    protected $fillable = ['user_id', 'action', 'sujet_type', 'sujet_id', 'resume', 'details'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sujet(): MorphTo
    {
        return $this->morphTo();
    }
}