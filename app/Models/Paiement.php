<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Paiement extends Model
{
    public const TYPE_JOURNALIER = 'journalier';
    public const TYPE_ABONNEMENT = 'abonnement';
    public const TYPE_VENTE = 'vente';
    public const TYPE_COACHING = 'coaching';

    public const TYPES = [
        self::TYPE_JOURNALIER => 'Entrée journalière',
        self::TYPE_ABONNEMENT => 'Abonnement',
        self::TYPE_VENTE => 'Vente comptoir',
        self::TYPE_COACHING => 'Coaching',
    ];

    public const MODES = [
        'especes' => 'Espèces',
        'orange_money' => 'Orange Money',
        'mtn_momo' => 'MTN MoMo',
        'moov_money' => 'Moov Money',
        'wave' => 'Wave',
        'carte' => 'Carte bancaire',
        'en_ligne' => 'Paiement en ligne',
    ];

    /** Modes proposés au comptoir (le paiement en ligne passe par l'espace membre). */
    public const MODES_CAISSE = ['especes', 'orange_money', 'mtn_momo', 'moov_money', 'wave', 'carte'];

    /** Modes encaissés en liquide (à remettre au tiroir), les autres sont électroniques. */
    public const MODES_ESPECES = ['especes'];

    protected $fillable = [
        'numero_recu', 'client_id', 'abonnement_id', 'user_id', 'caisse_id',
        'type', 'montant', 'mode', 'reference', 'annule_le', 'annule_par', 'motif_annulation',
    ];

    protected function casts(): array
    {
        return ['montant' => 'integer', 'annule_le' => 'datetime'];
    }

    public static function modesCaisse(): array
    {
        return array_intersect_key(self::MODES, array_flip(self::MODES_CAISSE));
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

    public function caisse(): BelongsTo
    {
        return $this->belongsTo(Caisse::class);
    }

    public function annulePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annule_par');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(PaiementLigne::class);
    }

    public function pack(): HasOne
    {
        return $this->hasOne(PackCoaching::class);
    }

    /** Paiements non annulés : les seuls qui comptent dans les recettes. */
    public function scopeValides(Builder $query): void
    {
        $query->whereNull('annule_le');
    }

    public function estAnnule(): bool
    {
        return $this->annule_le !== null;
    }

    /** Libellé court de l'objet du paiement. */
    public function objet(): string
    {
        return match ($this->type) {
            self::TYPE_ABONNEMENT => 'Abonnement '.($this->abonnement?->formule?->nom ?? ''),
            self::TYPE_COACHING => 'Coaching '.($this->pack?->formule?->nom ?? ''),
            self::TYPE_VENTE => $this->lignes->map(fn ($l) => $l->quantite.' × '.$l->libelle)->implode(', ') ?: 'Vente comptoir',
            default => 'Entrée journalière',
        };
    }

    public function getRouteKeyName(): string
    {
        return 'numero_recu';
    }
}
