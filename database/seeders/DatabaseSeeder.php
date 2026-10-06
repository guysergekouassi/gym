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
        User::firstOrCreate(['email' => 'admin@gymflow.local'], [
            'name' => 'Administrateur',
            'password' => 'ChangeMoi!2026',
            'role' => User::ROLE_ADMIN,
            'actif' => true,
            'doit_changer_mdp' => true,
        ]);

        User::firstOrCreate(['email' => 'caisse@gymflow.local'], [
            'name' => 'Caissière',
            'password' => 'ChangeMoi!2026',
            'role' => User::ROLE_CAISSIER,
            'actif' => true,
            'doit_changer_mdp' => true,
        ]);

        // Mots de passe provisoires : changement obligatoire à la première connexion.
        // Grille du Gymnase EPIKAÏZO, modifiable depuis le menu « Formules & tarifs »
        // [nom, prix, séances par jour (null = illimité), avantages]
        $serviettes = "2 serviettes offertes pendant la durée de l'abonnement";
        $formules = [
            ['Passe mensuelle Simple', 25000, 1, [
                "1 serviette offerte pendant la durée de l'abonnement",
            ]],
            ['Passe mensuelle Illimitée', 40000, null, [
                $serviettes,
                '2 séances de coaching individuel par mois + suivi collectif',
            ]],
            ['Passe mensuelle Généreux', 50000, null, [
                '5 parrainages gratuits par mois',
                'Parrainage : 1 personne par jour à moitié prix',
                "1 bidon d'eau offert à chaque séance",
                $serviettes,
                '3 séances de coaching individuel par mois + suivi collectif + bilan mensuel',
            ]],
            ['Passe mensuelle Privilège', 60000, null, [
                'Parrainage illimité : 1 invité gratuit chaque jour',
                "1 bidon d'eau offert à chaque séance",
                $serviettes,
                '4 séances de coaching individuel par mois + suivi collectif + bilan mensuel',
            ]],
        ];

        foreach ($formules as [$nom, $prix, $seancesParJour, $avantages]) {
            Formule::firstOrCreate(['nom' => $nom], [
                'duree_jours' => 30,
                'prix' => $prix,
                'seances_par_jour' => $seancesParJour,
                'description' => implode("\n", $avantages),
                'actif' => true,
            ]);
        }

        if (! Parametre::find(Parametre::TARIF_JOURNALIER)) {
            Parametre::definir(Parametre::TARIF_JOURNALIER, (int) config('salle.tarif_journalier'));
        }
    }
}
