<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Lecteur extends Model
{
    protected $fillable = ['nom', 'token_hash', 'actif', 'derniere_activite_at', 'salle_id'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'derniere_activite_at' => 'datetime',
        ];
    }

    public function salle(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Salle::class);
    }

    public function passages(): HasMany
    {
        return $this->hasMany(Passage::class);
    }

    /**
     * Crée un lecteur et renvoie le token en clair (affiché une seule fois).
     *
     * @return array{0: Lecteur, 1: string}
     */
    public static function creerAvecToken(string $nom, ?int $salleId = null): array
    {
        $token = Str::random(48);

        $lecteur = self::create([
            'nom' => $nom,
            'token_hash' => hash('sha256', $token),
            'actif' => true,
            'salle_id' => $salleId,
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
