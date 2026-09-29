<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    public const STATUTS = [
        'reservee' => 'Réservé',
        'attente' => 'Liste d’attente',
        'presente' => 'Présent',
        'absente' => 'Absent',
        'annulee' => 'Annulé',
    ];

    protected $fillable = ['cours_id', 'client_id', 'date', 'statut'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }
}