<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Article vendu au comptoir (boisson, complément, serviette…). */
class Produit extends Model
{
    protected $fillable = ['nom', 'prix', 'stock', 'seuil_alerte', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean', 'prix' => 'integer', 'stock' => 'integer', 'seuil_alerte' => 'integer'];
    }

    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementStock::class);
    }

    public function scopeEnAlerte(Builder $query): void
    {
        $query->where('actif', true)->whereColumn('stock', '<=', 'seuil_alerte');
    }
}