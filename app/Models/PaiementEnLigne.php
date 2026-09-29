<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Renouvellement payé depuis l'espace membre via l'agrégateur de paiement. */
class PaiementEnLigne extends Model
{
    protected $table = 'paiements_en_ligne';

    protected $fillable = ['transaction_id', 'client_id', 'formule_id', 'montant', 'statut', 'paiement_id', 'reponse'];

    protected function casts(): array
    {
        return ['reponse' => 'array', 'montant' => 'integer'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function formule(): BelongsTo
    {
        return $this->belongsTo(Formule::class);
    }

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }
}