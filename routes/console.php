<?php

use App\Models\Lecteur;
use Illuminate\Support\Facades\Artisan;

Artisan::command('salle:lecteur {nom : Nom du lecteur, ex. "Entrée principale"}', function (string $nom) {
    [$lecteur, $token] = Lecteur::creerAvecToken($nom);

    $this->info("Lecteur « {$lecteur->nom} » créé (id {$lecteur->id}).");
    $this->warn("Token (copie-le maintenant, il ne sera plus affiché) : {$token}");
})->purpose('Créer un lecteur d\'empreinte et générer son token API');
