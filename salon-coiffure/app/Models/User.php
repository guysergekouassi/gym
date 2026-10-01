<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_GERANTE = 'gerante';

    public const ROLE_ASSISTANTE = 'assistante';

    protected $fillable = ['name', 'email', 'password', 'role', 'actif'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'actif' => 'boolean',
        ];
    }

    public function isGerante(): bool
    {
        return $this->role === self::ROLE_GERANTE;
    }

    public function ventes(): HasMany
    {
        return $this->hasMany(Vente::class);
    }

    public function scopeAssistantes(Builder $query): Builder
    {
        return $query->where('role', self::ROLE_ASSISTANTE);
    }

    public function pageAccueil(): string
    {
        return $this->isGerante() ? route('dashboard') : route('caisse.index');
    }
}
