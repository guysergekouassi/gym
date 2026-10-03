<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Lecteur extends Model
{
    protected $fillable = ['nom', 'numero_serie', 'adresse_ip', 'token_hash', 'actif', 'derniere_activite_at', 'stamp_pointages'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'derniere_activite_at' => 'datetime',
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

    /** Pointeuses en réseau joignables par le protocole Cloud (ADMS). */
    public function scopePointeuses($query): void
    {
        $query->where('actif', true)->whereNotNull('numero_serie');
    }

    public function estEnLigne(): bool
    {
        return $this->derniere_activite_at !== null && $this->derniere_activite_at->gt(now()->subMinutes(2));
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
