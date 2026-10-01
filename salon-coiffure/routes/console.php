<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

Artisan::command('salon:mot-de-passe {email} {motdepasse}', function (string $email, string $motdepasse) {
    $user = User::where('email', $email)->first();

    if (! $user) {
        $this->error("Aucun compte avec l'e-mail {$email}.");

        return 1;
    }

    $user->update(['password' => $motdepasse, 'actif' => true]);
    $this->info("Mot de passe de {$user->name} réinitialisé.");
})->purpose('Réinitialiser le mot de passe d\'un compte (gérante qui a oublié le sien)');
