<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coach extends Model
{
    protected $table = 'coachs';

    protected $fillable = ['nom', 'telephone', 'specialite', 'commission_pct', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean', 'commission_pct' => 'integer'];
    }

    public function cours(): HasMany
    {
        return $this->hasMany(Cours::class);
    }

    public function packs(): HasMany
    {
        return $this->hasMany(PackCoaching::class);
    }

    public function seances(): HasMany
    {
        return $this->hasMany(SeanceCoaching::class);
    }
}