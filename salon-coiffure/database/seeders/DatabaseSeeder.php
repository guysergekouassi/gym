<?php

namespace Database\Seeders;

use App\Models\Categorie;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(['email' => 'gerante@salon.local'], [
            'name' => 'Gérante',
            'password' => 'ChangeMoi!2026',
            'role' => User::ROLE_GERANTE,
            'actif' => true,
        ]);

        // Liste d'exemple : la gérante modifie noms et prix dans le menu « Services »
        $catalogue = [
            ['Coiffure', '#2563eb', [
                ['COI-001', 'Brushing', 3000],
                ['COI-002', 'Shampoing + brushing', 4000],
                ['COI-003', 'Défrisage', 5000],
                ['COI-004', 'Mise en plis', 4000],
                ['COI-005', 'Coloration', 8000],
                ['COI-006', 'Coupe femme', 3000],
            ]],
            ['Tresses & tissages', '#9333ea', [
                ['TRE-001', 'Nattes collées', 5000],
                ['TRE-002', 'Tresses africaines', 10000],
                ['TRE-003', 'Box braids', 15000],
                ['TRE-004', 'Tissage', 7000],
                ['TRE-005', 'Pose perruque', 5000],
                ['TRE-006', 'Vanilles', 8000],
            ]],
            ['Soins', '#059669', [
                ['SOI-001', 'Soin capillaire', 5000],
                ['SOI-002', 'Bain d\'huile', 3000],
                ['SOI-003', 'Soin visage', 7000],
            ]],
            ['Ongles', '#db2777', [
                ['ONG-001', 'Manucure', 3000],
                ['ONG-002', 'Pédicure', 4000],
                ['ONG-003', 'Pose vernis semi-permanent', 5000],
                ['ONG-004', 'Pose faux ongles', 7000],
            ]],
            ['Barbier', '#ea580c', [
                ['BAR-001', 'Coupe homme', 2000],
                ['BAR-002', 'Coupe enfant', 1500],
                ['BAR-003', 'Taille de barbe', 1000],
                ['BAR-004', 'Coupe + barbe', 2500],
            ]],
        ];

        foreach ($catalogue as $ordre => [$nom, $couleur, $services]) {
            $categorie = Categorie::firstOrCreate(['nom' => $nom], ['couleur' => $couleur, 'ordre' => $ordre + 1]);

            foreach ($services as $rang => [$code, $libelle, $prix]) {
                Service::firstOrCreate(['code' => $code], [
                    'categorie_id' => $categorie->id,
                    'nom' => $libelle,
                    'prix' => $prix,
                    'ordre' => $rang + 1,
                ]);
            }
        }

        $this->command?->warn('Compte gérante : gerante@salon.local / ChangeMoi!2026 — changez le mot de passe dès la première connexion.');
    }
}
