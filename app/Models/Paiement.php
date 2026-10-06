<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Paiement extends Model
{
    public const TYPE_JOURNALIER = 'journalier';
    public const TYPE_ABONNEMENT = 'abonnement';
    /** Carnet Fidélité : séances payées d'avance (quantite = nombre de séances), décomptées à chaque arrivée. */
    public const TYPE_CARNET = 'carnet';

    public const TYPES = [
        self::TYPE_JOURNALIER => 'Entrée journalière',
        self::TYPE_ABONNEMENT => 'Abonnement',
        self::TYPE_CARNET => 'Carnet Fidélité',
    ];

    /** Ventes de séances à l'unité (comptées avec les recettes journalières). */
    public const TYPES_PASSAGE = [self::TYPE_JOURNALIER, self::TYPE_CARNET];

    public const MODES = [
        'especes' => 'Espèces',
        'orange_money' => 'Orange Money',
        'mtn_momo' => 'MTN MoMo',
        'moov_money' => 'Moov Money',
        'wave' => 'Wave',
        'carte' => 'Carte bancaire',
    ];

    protected $fillable = [
        'numero_recu', 'client_id', 'abonnement_id', 'user_id',
        'type', 'montant', 'quantite', 'mode', 'reference',
        'annule_le', 'annule_par', 'motif_annulation',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'quantite' => 'integer',
            'annule_le' => 'datetime',
        ];
    }

    /** Encaissements non annulés : seuls ceux-là comptent dans la recette. */
    public function scopeValides(Builder $query): void
    {
        $query->whereNull('annule_le');
    }

    public function estAnnule(): bool
    {
        return $this->annule_le !== null;
    }

    public function annulePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annule_par');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function abonnement(): BelongsTo
    {
        return $this->belongsTo(Abonnement::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Passages rattachés : ticket du jour, ou séances consommées sur un carnet. */
    public function passages(): HasMany
    {
        return $this->hasMany(Passage::class);
    }

    public function estCarnet(): bool
    {
        return $this->type === self::TYPE_CARNET;
    }

    /** Séances encore disponibles sur un carnet : achetées moins arrivées déjà décomptées. */
    public function seancesRestantes(): int
    {
        if (! $this->estCarnet() || $this->estAnnule()) {
            return 0;
        }

        $utilisees = $this->seances_utilisees ?? $this->passages()->where('sens', Passage::SENS_ENTREE)->count();

        return max(0, $this->quantite - (int) $utilisees);
    }

    /** Libellé de ce qui a été payé (ticket, listes, exports). */
    public function objet(): string
    {
        return match (true) {
            $this->abonnement_id !== null => 'Abonnement '.$this->abonnement?->formule?->nom,
            $this->estCarnet() => "Carnet Fidélité · {$this->quantite} séances",
            default => 'Entrée journalière'.($this->quantite > 1 ? ' × '.$this->quantite : ''),
        };
    }

    public function getRouteKeyName(): string
    {
        return 'numero_recu';
    }
}
