<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class CompteService
{
    public function maximum(): int
    {
        return config('salon.max_assistantes');
    }

    public function nombreActives(): int
    {
        return User::assistantes()->where('actif', true)->count();
    }

    public function peutAjouter(): bool
    {
        return $this->nombreActives() < $this->maximum();
    }

    public function creerAssistante(array $data): User
    {
        $this->verifierPlace();

        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => User::ROLE_ASSISTANTE,
            'actif' => true,
        ]);
    }

    public function basculerActivation(User $assistante): void
    {
        if (! $assistante->actif) {
            $this->verifierPlace();
        }

        $assistante->update(['actif' => ! $assistante->actif]);
    }

    private function verifierPlace(): void
    {
        if (! $this->peutAjouter()) {
            throw ValidationException::withMessages([
                'name' => "Limite atteinte : {$this->maximum()} comptes assistantes actifs au maximum. Désactivez un compte avant d'en ajouter un.",
            ]);
        }
    }
}
