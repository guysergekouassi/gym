<?php

use App\Models\Lecteur;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('salle:lecteur {nom : Nom du lecteur, ex. "Entrée principale"}', function (string $nom) {
    [$lecteur, $token] = Lecteur::creerAvecToken($nom);

    $this->info("Lecteur « {$lecteur->nom} » créé (id {$lecteur->id}).");
    $this->warn("Token (copie-le maintenant, il ne sera plus affiché) : {$token}");
})->purpose('Créer un lecteur d\'empreinte ou de badge et générer son token API');

Artisan::command('salle:rappels', function (MessageService $messages) {
    $compte = $messages->genererRappels();

    $this->info('Rappels préparés : '.collect($compte)->map(fn ($n, $type) => "{$type} {$n}")->implode(', '));
})->purpose('Préparer les rappels du jour : échéances, inactifs, anniversaires, essais');

Artisan::command('salle:mot-de-passe {email : E-mail du compte}', function (string $email) {
    $user = User::where('email', $email)->first();
    if (! $user) {
        $this->error("Aucun compte avec l'e-mail {$email}.");

        return 1;
    }

    $motDePasse = Str::password(12, symbols: false);
    $user->update(['password' => $motDePasse, 'actif' => true]);
    $this->info("Nouveau mot de passe de {$user->name} : {$motDePasse}");
    $this->warn('Changez-le dès la connexion (menu Mon compte).');
})->purpose('Réinitialiser le mot de passe d\'un compte (admin ou caissière)');

// Rappels tous les matins (nécessite la tâche planifiée « php artisan schedule:run » chaque minute)
Schedule::command('salle:rappels')->dailyAt(config('salle.messagerie.heure_rappels', '08:00'));
