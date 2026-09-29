<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeanceCoaching extends Model
{
    protected $table = 'seances_coaching';

    protected $fillable = ['pack_id', 'coach_id', 'faite_le', 'user_id'];

    protected function casts(): array
    {
        return ['faite_le' => 'datetime'];
    }

    public function pack(): BelongsTo
    {
        return $this->belongsTo(PackCoaching::class, 'pack_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }
}