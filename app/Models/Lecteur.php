<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Lecteur extends Model
{
    protected $fillable = [
        'nom', 'numero_serie', 'adresse_ip', 'port', 'identifiant', 'mot_de_passe', 'modele',
        'token_hash', 'actif', 'derniere_activite_at', 'dernier_evenement_le', 'derniere_erreur',
    ];

    // Ni le token ni le mot de passe de la pointeuse ne sortent jamais du serveur
    protected $hidden = ['token_hash', 'mot_de_passe'];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'port' => 'integer',
            'mot_de_passe' => 'encrypted', // chiffré en base avec la clé de l'application
            'derniere_activite_at' => 'datetime',
            'dernier_evenement_le' => 'datetime',
        ];
    }

    public function passages(): HasMany
    {
        return $this->hasMany(Passage::class);
    }

    public function commandes(): HasMany
    {
        return $this->hasMany(CommandePointeuse::class);
    }

    /** Pointeuses Hikvision configurées (adresse IP + mot de passe). */
    public function scopePointeuses($query): void
    {
        $query->where('actif', true)->whereNotNull('adresse_ip')->whereNotNull('mot_de_passe');
    }

    public function estPointeuse(): bool
    {
        return $this->adresse_ip !== null && $this->mot_de_passe !== null;
    }

    public function estEnLigne(): bool
    {
        return $this->derniere_activite_at !== null && $this->derniere_activite_at->gt(now()->subMinute());
    }

    /**
     * Crée un lecteur et renvoie le token en clair (affiché une seule fois).
     *
     * @return array{0: Lecteur, 1: string}
     */
    public static function creerAvecToken(string $nom): array
    {
        $token = Str::random(48);

        $lecteur = self::create([
            'nom' => $nom,
            'token_hash' => hash('sha256', $token),
            'actif' => true,
        ]);

        return [$lecteur, $token];
    }

    public static function trouverParToken(?string $token): ?self
    {
        if (! $token) {
            return null;
        }

        return self::where('token_hash', hash('sha256', $token))->where('actif', true)->first();
    }
}
