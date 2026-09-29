<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Abonnement extends Model
{
    public const STATUT_ACTIF = 'actif';
    public const STATUT_ANNULE = 'annule';

    protected $fillable = [
        'client_id', 'formule_id', 'date_debut', 'date_fin', 'montant', 'entrees_restantes',
        'remise', 'frais_inscription', 'code_promo_id', 'statut', 'est_renouvellement', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'montant' => 'integer',
            'entrees_restantes' => 'integer',
            'remise' => 'integer',
            'frais_inscription' => 'integer',
            'est_renouvellement' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function formule(): BelongsTo
    {
        return $this->belongsTo(Formule::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function codePromo(): BelongsTo
    {
        return $this->belongsTo(CodePromo::class);
    }

    public function paiement(): HasOne
    {
        return $this->hasOne(Paiement::class);
    }

    public function gels(): HasMany
    {
        return $this->hasMany(Gel::class);
    }

    /** Abonnements valides à une date donnée (aujourd'hui par défaut). */
    public function scopeEnCours(Builder $query, ?CarbonInterface $date = null): void
    {
        $jour = ($date ?? today())->toDateString();

        $query->where('statut', self::STATUT_ACTIF)
            ->whereDate('date_debut', '<=', $jour)
            ->whereDate('date_fin', '>=', $jour);
    }

    /** Abonnements à la durée (hors carnets d'entrées). */
    public function scopeDuree(Builder $query): void
    {
        $query->whereNull('entrees_restantes');
    }

    public function estCarnet(): bool
    {
        return $this->entrees_restantes !== null;
    }

    public function joursRestants(): int
    {
        return max(0, (int) today()->diffInDays($this->date_fin, false));
    }
}
