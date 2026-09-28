<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Paiement extends Model
{
    public const TYPE_JOURNALIER = 'journalier';
    public const TYPE_ABONNEMENT = 'abonnement';

    public const TYPES = [
        self::TYPE_JOURNALIER => 'Entrée journalière',
        self::TYPE_ABONNEMENT => 'Abonnement',
    ];

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
        'type', 'montant', 'mode', 'reference',
    ];

    protected function casts(): array
    {
        return ['montant' => 'integer'];
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

    public function getRouteKeyName(): string
    {
        return 'numero_recu';
    }
}
