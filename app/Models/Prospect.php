<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Personne intéressée, pas encore inscrite : suivi jusqu'à l'inscription. */
class Prospect extends Model
{
    public const STATUTS = [
        'nouveau' => 'Nouveau',
        'essai_prevu' => 'Essai prévu',
        'essai_fait' => 'Essai fait',
        'inscrit' => 'Inscrit',
        'perdu' => 'Perdu',
    ];

    public const SOURCES = ['Passage à la salle', 'Bouche-à-oreille', 'Parrainage', 'Facebook', 'Instagram', 'TikTok', 'WhatsApp', 'Autre'];

    protected $fillable = ['nom', 'telephone', 'source', 'statut', 'essai_le', 'notes', 'client_id', 'user_id'];

    protected function casts(): array
    {
        return ['essai_le' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}