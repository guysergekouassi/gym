<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Formule extends Model
{
    protected $fillable = ['nom', 'duree_jours', 'prix', 'actif'];

    protected function casts(): array
    {
        return [
            'duree_jours' => 'integer',
            'prix' => 'integer',
            'actif' => 'boolean',
        ];
    }

    public function abonnements(): HasMany
    {
        return $this->hasMany(Abonnement::class);
    }
}
