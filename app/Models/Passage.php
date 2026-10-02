<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Passage extends Model
{
    public const METHODE_EMPREINTE = 'empreinte';
    public const METHODE_CAISSE = 'caisse';
    public const METHODE_CARTE = 'carte';

    public const METHODES = [
        self::METHODE_EMPREINTE => 'Empreinte',
        self::METHODE_CARTE => 'Carte / QR',
        self::METHODE_CAISSE => 'Caisse',
    ];

    public const STATUT_AUTORISE = 'autorise';
    public const STATUT_REFUSE = 'refuse';

    public const MOTIFS = [
        'empreinte_inconnue' => 'Empreinte non reconnue',
        'carte_inconnue' => 'Carte ou code non reconnu',
        'abonnement_expire' => 'Abonnement expiré ou inexistant',
        'abonnement_gele' => 'Abonnement gelé',
        'carnet_epuise' => 'Carnet d’entrées épuisé',
        'paiement_requis' => 'Paiement journalier requis à la caisse',
    ];

    protected $fillable = [
        'client_id', 'lecteur_id', 'user_id', 'paiement_id', 'salle_id',
        'methode', 'statut', 'motif', 'empreinte_id', 'passe_le', 'sorti_le',
    ];

    protected function casts(): array
    {
        return [
            'passe_le'  => 'datetime',
            'sorti_le'  => 'datetime',
        ];
    }

    /** Vrai si ce passage est une sortie (le client a re-scanné après son entrée). */
    public function estSortie(): bool
    {
        return $this->sorti_le !== null;
    }

    /** Durée passée dans la salle en minutes (null si pas encore sorti). */
    public function dureeMinutes(): ?int
    {
        if (! $this->sorti_le) {
            return null;
        }

        return (int) $this->passe_le->diffInMinutes($this->sorti_le);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function lecteur(): BelongsTo
    {
        return $this->belongsTo(Lecteur::class);
    }

    public function salle(): BelongsTo
    {
        return $this->belongsTo(Salle::class);
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

    /** Libellé du moyen d'identification : « Empreinte n° 12 », « Carte 0012… », « Caisse ». */
    public function identification(): string
    {
        return match ($this->methode) {
            self::METHODE_CAISSE => 'Caisse',
            self::METHODE_CARTE => 'Carte '.$this->empreinte_id,
            default => 'Empreinte n° '.($this->empreinte_id ?? '—'),
        };
    }

    public function message(): string
    {
        if ($this->estAutorise()) {
            return $this->estSortie() ? 'Bonne journée !' : 'Bienvenue';
        }

        return self::MOTIFS[$this->motif] ?? 'Accès refusé';
    }
}
