<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_CAISSIER = 'caissier';

    public const ROLES = [
        self::ROLE_ADMIN => 'Administrateur',
        self::ROLE_CAISSIER => 'Caissière',
    ];

    protected $fillable = ['name', 'email', 'password', 'role', 'actif', 'caisse_id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'actif' => 'boolean',
        ];
    }

    public function caisse(): BelongsTo
    {
        return $this->belongsTo(Caisse::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isCaissier(): bool
    {
        return $this->role === self::ROLE_CAISSIER;
    }

    /** Caisse sur laquelle la caissière peut encaisser (attribuée et active). */
    public function caisseActive(): ?Caisse
    {
        return $this->caisse?->actif ? $this->caisse : null;
    }
}
