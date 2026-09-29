<?php

namespace Database\Seeders;

use App\Models\Caisse;
use App\Models\Formule;
use App\Models\Lecteur;
use App\Models\Salle;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $salle = Salle::firstOrCreate(['nom' => config('salle.nom')], ['adresse' => config('salle.adresse'), 'actif' => true]);

        // Comptes créés une seule fois : relancer le seeder ne change jamais un mot de passe existant
        User::firstOrCreate(['email' => 'admin@gymflow.local'], [
            'name' => 'Administrateur',
            'password' => 'ChangeMoi!2026',
            'role' => User::ROLE_ADMIN,
            'actif' => true,
        ]);

        $caisse = Caisse::firstOrCreate(['nom' => 'Caisse principale'], ['emplacement' => 'Accueil', 'actif' => true, 'salle_id' => $salle->id]);
        $caisse->salle_id ??= $salle->id;
        $caisse->save();

        User::firstOrCreate(['email' => 'caisse@gymflow.local'], [
            'name' => 'Caissière',
            'password' => 'ChangeMoi!2026',
            'role' => User::ROLE_CAISSIER,
            'caisse_id' => $caisse->id,
            'actif' => true,
        ]);

        // Tarifs d'exemple, à adapter dans Formules et promos
        $formules = [
            ['Mensuel', Formule::TYPE_ABONNEMENT, 30, 15000, null, null],
            ['Trimestriel', Formule::TYPE_ABONNEMENT, 90, 40000, null, null],
            ['Semestriel', Formule::TYPE_ABONNEMENT, 180, 75000, null, null],
            ['Annuel', Formule::TYPE_ABONNEMENT, 365, 140000, null, null],
            ['Carnet 10 entrées', Formule::TYPE_CARNET, 90, 17000, 10, null],
            ['Coaching 8 séances', Formule::TYPE_COACHING, 60, 60000, null, 8],
        ];

        foreach ($formules as [$nom, $type, $duree, $prix, $entrees, $seances]) {
            Formule::firstOrCreate(['nom' => $nom], [
                'type' => $type,
                'categorie' => 'Standard',
                'duree_jours' => $duree,
                'prix' => $prix,
                'nb_entrees' => $entrees,
                'nb_seances' => $seances,
                'actif' => true,
            ]);
        }

        if (Lecteur::count() === 0) {
            [$lecteur, $token] = Lecteur::creerAvecToken('Entrée principale', $salle->id);
            $this->command?->warn("Token du lecteur « {$lecteur->nom} » (à copier, affiché une seule fois) : {$token}");
        }
    }
}
