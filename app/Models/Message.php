<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Message à envoyer à un membre ou un prospect (WhatsApp ou SMS). */
class Message extends Model
{
    public const TYPES = [
        'expiration' => 'Échéance',
        'inactif' => 'Inactif',
        'anniversaire' => 'Anniversaire',
        'essai' => 'Séance d’essai',
        'recu' => 'Reçu',
        'campagne' => 'Campagne',
        'lien_membre' => 'Lien espace membre',
    ];

    protected $fillable = [
        'client_id', 'prospect_id', 'type', 'canal', 'telephone', 'contenu', 'statut',
        'envoye_le', 'user_id', 'erreur', 'campagne_id', 'pour_le',
    ];

    protected function casts(): array
    {
        return ['envoye_le' => 'datetime', 'pour_le' => 'date'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    public function scopeAEnvoyer(Builder $query): void
    {
        $query->where('statut', 'a_envoyer')->whereDate('pour_le', '<=', today()->toDateString());
    }

    /** Lien wa.me qui ouvre WhatsApp avec le texte déjà saisi. */
    public function lienWhatsapp(): ?string
    {
        $chiffres = preg_replace('/\D/', '', (string) $this->telephone);
        if ($chiffres === '') {
            return null;
        }
        if (strlen($chiffres) === 10) {
            $chiffres = config('salle.indicatif', '225').$chiffres;
        }

        return 'https://wa.me/'.$chiffres.'?text='.rawurlencode($this->contenu);
    }
}