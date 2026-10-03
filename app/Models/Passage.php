<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Passage extends Model
{
    public const METHODE_BADGE = 'badge';
    public const METHODE_CAISSE = 'caisse';

    public const STATUT_AUTORISE = 'autorise';
    public const STATUT_REFUSE = 'refuse';

    public const MOTIFS = [
        'badge_inconnu' => 'Badge non reconnu',
        'abonnement_expire' => 'Abonnement expiré ou inexistant',
        'paiement_requis' => 'Paiement journalier requis à la caisse',
    ];

    protected $fillable = [
        'client_id', 'lecteur_id', 'user_id', 'paiement_id',
        'methode', 'statut', 'motif', 'badge_id', 'passe_le',
    ];

    protected function casts(): array
    {
        return ['passe_le' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function lecteur(): BelongsTo
    {
        return $this->belongsTo(Lecteur::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }

    public function estAutorise(): bool
    {
        return $this->statut === self::STATUT_AUTORISE;
    }

    public function message(): string
    {
        if ($this->estAutorise()) {
            return 'Bienvenue';
        }

        return self::MOTIFS[$this->motif] ?? 'Accès refusé';
    }
}
