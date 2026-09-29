<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Salle extends Model
{
    protected $fillable = ['nom', 'adresse', 'telephone', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function caisses(): HasMany
    {
        return $this->hasMany(Caisse::class);
    }

    public function lecteurs(): HasMany
    {
        return $this->hasMany(Lecteur::class);
    }
}