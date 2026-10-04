<?php

namespace Database\Seeders;

use App\Models\Formule;
use App\Models\Parametre;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(['email' => 'admin@salle.local'], [
            'name' => 'Administrateur',
            'password' => 'ChangeMoi!2026',
            'role' => User::ROLE_ADMIN,
            'actif' => true,
            'doit_changer_mdp' => true,
        ]);

        User::firstOrCreate(['email' => 'caisse@salle.local'], [
            'name' => 'Caissière',
            'password' => 'ChangeMoi!2026',
            'role' => User::ROLE_CAISSIER,
            'actif' => true,
            'doit_changer_mdp' => true,
        ]);

        // Mots de passe provisoires : changement obligatoire à la première connexion.
        // Tarifs d'exemple, à adapter depuis le menu « Formules & tarifs »
        $formules = [
            ['Mensuel', 30, 15000],
            ['Trimestriel', 90, 40000],
            ['Semestriel', 180, 75000],
            ['Annuel', 365, 140000],
        ];

        foreach ($formules as [$nom, $duree, $prix]) {
            Formule::firstOrCreate(['nom' => $nom], [
                'duree_jours' => $duree,
                'prix' => $prix,
                'actif' => true,
            ]);
        }

        if (! Parametre::find(Parametre::TARIF_JOURNALIER)) {
            Parametre::definir(Parametre::TARIF_JOURNALIER, (int) config('salle.tarif_journalier'));
        }
    }
}
