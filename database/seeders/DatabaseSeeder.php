<?php

namespace Database\Seeders;

use App\Models\Formule;
use App\Models\Lecteur;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email' => 'admin@gymflow.local'], [
            'name' => 'Administrateur',
            'password' => 'ChangeMoi!2026',
            'role' => User::ROLE_ADMIN,
            'actif' => true,
        ]);

        User::updateOrCreate(['email' => 'caisse@gymflow.local'], [
            'name' => 'Caissière',
            'password' => 'ChangeMoi!2026',
            'role' => User::ROLE_CAISSIER,
            'actif' => true,
        ]);

        // Tarifs d'exemple, à adapter
        $formules = [
            ['Mensuel', 30, 15000],
            ['Trimestriel', 90, 40000],
            ['Semestriel', 180, 75000],
            ['Annuel', 365, 140000],
        ];

        foreach ($formules as [$nom, $duree, $prix]) {
            Formule::updateOrCreate(['nom' => $nom], [
                'duree_jours' => $duree,
                'prix' => $prix,
                'actif' => true,
            ]);
        }

        if (Lecteur::count() === 0) {
            [$lecteur, $token] = Lecteur::creerAvecToken('Entrée principale');
            $this->command?->warn("Token du lecteur « {$lecteur->nom} » (à copier, affiché une seule fois) : {$token}");
        }
    }
}
