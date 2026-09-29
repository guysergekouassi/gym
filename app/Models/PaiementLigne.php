<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaiementLigne extends Model
{
    protected $fillable = ['paiement_id', 'produit_id', 'libelle', 'quantite', 'prix_unitaire'];

    protected function casts(): array
    {
        return ['quantite' => 'integer', 'prix_unitaire' => 'integer'];
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }
}