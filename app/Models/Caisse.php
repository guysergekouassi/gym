<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Point d'encaissement (tiroir-caisse) auquel sont rattachées une ou plusieurs caissières. */
class Caisse extends Model
{
    protected $fillable = ['nom', 'emplacement', 'actif', 'salle_id'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function salle(): BelongsTo
    {
        return $this->belongsTo(Salle::class);
    }

    public function clotures(): HasMany
    {
        return $this->hasMany(Cloture::class);
    }

    /** Journée déjà clôturée : plus aucun encaissement possible sur cette caisse. */
    public function estClotureeLe(?\Carbon\CarbonInterface $jour = null): bool
    {
        return $this->clotures()->whereDate('jour', ($jour ?? today())->toDateString())->exists();
    }

    public function caissiers(): HasMany
    {
        return $this->hasMany(User::class)->where('role', User::ROLE_CAISSIER);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    public function scopeActives(Builder $query): void
    {
        $query->where('actif', true);
    }
}
