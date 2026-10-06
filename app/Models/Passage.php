<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Passage extends Model
{
    public const METHODE_EMPREINTE = 'empreinte';
    public const METHODE_CAISSE = 'caisse';

    public const STATUT_AUTORISE = 'autorise';
    public const STATUT_REFUSE = 'refuse';

    /** 1er badge du jour = arrivée, 2e = départ, les suivants = séance déjà enregistrée. */
    public const SENS_ENTREE = 'entree';
    public const SENS_DEPART = 'depart';
    public const SENS_DEJA = 'deja';

    public const MOTIFS = [
        'empreinte_inconnue' => 'Empreinte non reconnue',
        'abonnement_expire' => 'Abonnement expiré ou inexistant',
        'paiement_requis' => 'Paiement journalier requis à la caisse',
        'carnet_epuise' => 'Carnet Fidélité épuisé : passez à la caisse',
        'seance_du_jour_faite' => 'Séance du jour déjà faite (passe 1 séance par jour)',
        'refus_pointeuse' => 'Accès refusé par la pointeuse',
    ];

    protected $fillable = [
        'client_id', 'lecteur_id', 'user_id', 'paiement_id',
        'methode', 'statut', 'sens', 'motif', 'empreinte_id', 'passe_le',
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

    /** Venues : passages autorisés hors départs et badges en trop (un membre = une venue par jour). */
    public function scopeVenues(\Illuminate\Database\Eloquent\Builder $q): void
    {
        $q->where('statut', self::STATUT_AUTORISE)
            ->where(fn ($w) => $w->whereNull('sens')->orWhere('sens', self::SENS_ENTREE));
    }

    public function estAutorise(): bool
    {
        return $this->statut === self::STATUT_AUTORISE;
    }

    public function message(): string
    {
        if ($this->estAutorise()) {
            return match ($this->sens) {
                self::SENS_DEPART => 'À bientôt',
                self::SENS_DEJA => 'Déjà enregistré',
                default => 'Bienvenue',
            };
        }

        return self::MOTIFS[$this->motif] ?? 'Accès refusé';
    }
}
