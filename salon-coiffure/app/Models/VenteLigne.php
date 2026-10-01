<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenteLigne extends Model
{
    public $timestamps = false;

    protected $fillable = ['vente_id', 'service_id', 'categorie_id', 'libelle', 'prix_unitaire', 'quantite', 'total'];

    protected function casts(): array
    {
        return [
            'prix_unitaire' => 'integer',
            'quantite' => 'integer',
            'total' => 'integer',
        ];
    }

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Vente::class);
    }
}
