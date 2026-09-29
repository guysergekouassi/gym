<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Pack de séances de coaching personnel vendu à un client. */
class PackCoaching extends Model
{
    protected $table = 'packs_coaching';

    protected $fillable = ['client_id', 'coach_id', 'formule_id', 'paiement_id', 'seances_total', 'seances_restantes', 'montant', 'expire_le', 'statut'];

    protected function casts(): array
    {
        return ['expire_le' => 'date', 'seances_total' => 'integer', 'seances_restantes' => 'integer', 'montant' => 'integer'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }

    public function formule(): BelongsTo
    {
        return $this->belongsTo(Formule::class);
    }

    public function seances(): HasMany
    {
        return $this->hasMany(SeanceCoaching::class, 'pack_id');
    }

    /** Valeur d'une séance, pour le calcul des commissions. */
    public function prixSeance(): int
    {
        return $this->seances_total > 0 ? intdiv($this->montant, $this->seances_total) : 0;
    }
}