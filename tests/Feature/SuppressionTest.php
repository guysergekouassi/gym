<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Formule;
use App\Models\Lecteur;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuppressionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $caissiere;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        User::query()->update(['doit_changer_mdp' => false]);
        $this->admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();
        $this->caissiere = User::where('role', User::ROLE_CAISSIER)->firstOrFail();
    }

    public function test_masquer_reafficher_et_supprimer_un_client(): void
    {
        $doublon = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Doublon', 'empreinte_id' => '5']);
        $payeur = Client::create(['type' => Client::TYPE_JOURNALIER, 'nom' => 'Payeur']);
        $this->actingAs($this->caissiere)->post('/caisse/journalier', ['client_id' => $payeur->id, 'mode' => 'especes']);

        // La caissière ne peut ni masquer ni supprimer
        $this->delete("/clients/{$doublon->id}")->assertForbidden();
        $this->delete("/clients/{$doublon->id}/definitif")->assertForbidden();

        $this->actingAs($this->admin)->delete("/clients/{$doublon->id}")->assertRedirect();
        $this->assertSoftDeleted($doublon);
        $this->get('/clients?statut=masques')->assertOk()->assertSee('Doublon')->assertSee('Réafficher');

        $this->post("/clients/{$doublon->id}/restaurer")->assertRedirect(route('clients.show', $doublon));
        $this->assertNotSoftDeleted($doublon);

        // Client avec paiement : suppression définitive refusée (la caisse resterait fausse)
        $this->delete("/clients/{$payeur->id}/definitif")->assertSessionHas('erreur');
        $this->assertDatabaseHas('clients', ['id' => $payeur->id]);
        $this->assertSame(1, Paiement::count());

        $this->delete("/clients/{$doublon->id}/definitif")->assertSessionHas('succes');
        $this->assertDatabaseMissing('clients', ['id' => $doublon->id]);
    }

    public function test_supprimer_formule_compte_et_pointeuse(): void
    {
        $neuve = Formule::create(['nom' => 'Essai', 'duree_jours' => 7, 'prix' => 5000, 'actif' => true]);
        $vendue = Formule::where('nom', 'Mensuel')->firstOrFail();
        $client = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'A']);
        $this->actingAs($this->caissiere)->post('/caisse/abonnement', ['client_id' => $client->id, 'formule_id' => $vendue->id, 'mode' => 'especes']);
        $this->delete("/admin/formules/{$neuve->id}")->assertForbidden();

        $this->actingAs($this->admin);
        $this->delete("/admin/formules/{$vendue->id}")->assertSessionHas('erreur');
        $this->delete("/admin/formules/{$neuve->id}")->assertSessionHas('succes');
        $this->assertDatabaseMissing('formules', ['id' => $neuve->id]);

        // Compte qui a encaissé : refusé ; compte neuf : supprimé ; soi-même : refusé
        $this->delete("/admin/utilisateurs/{$this->caissiere->id}/definitif")->assertSessionHas('erreur');
        $neuf = User::create(['name' => 'Neuf', 'email' => 'n@x.ci', 'password' => 'Secret12345', 'role' => User::ROLE_CAISSIER]);
        $this->delete("/admin/utilisateurs/{$neuf->id}/definitif")->assertSessionHas('succes');
        $this->delete("/admin/utilisateurs/{$this->admin->id}/definitif")->assertSessionHas('erreur');
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);

        $this->post('/admin/pointeuses', ['nom' => 'P', 'adresse_ip' => '192.168.1.64', 'port' => 80, 'identifiant' => 'admin', 'mot_de_passe' => 'x']);
        $pointeuse = Lecteur::where('adresse_ip', '192.168.1.64')->firstOrFail();
        $this->delete("/admin/pointeuses/{$pointeuse->id}/definitif")->assertSessionHas('succes');
        $this->assertDatabaseMissing('lecteurs', ['id' => $pointeuse->id]);
    }

    public function test_sauvegarde_quotidienne(): void
    {
        $dossier = storage_path('app/sauvegardes');
        \Illuminate\Support\Facades\File::deleteDirectory($dossier);

        // Base de test en mémoire : on vérifie simplement que la commande s'exécute
        $this->artisan('salle:sauvegarder')->assertSuccessful();

        \Illuminate\Support\Facades\File::deleteDirectory($dossier);
    }

    public function test_effacer_les_donnees_de_test(): void
    {
        $this->actingAs($this->admin)->post('/admin/pointeuses', [
            'nom' => 'Entrée', 'adresse_ip' => '192.168.50.64', 'port' => 80, 'identifiant' => 'admin', 'mot_de_passe' => 'Secret.Pointeuse1',
        ]);
        $pointeuse = Lecteur::where('adresse_ip', '192.168.50.64')->firstOrFail();
        $client = Client::create(['nom' => 'Test', 'type' => Client::TYPE_ABONNE, 'empreinte_id' => '1']);
        $this->actingAs($this->caissiere)->post('/caisse/abonnement', ['client_id' => $client->id, 'formule_id' => Formule::value('id'), 'mode' => 'especes']);
        $this->post('/caisse/journalier', ['mode' => 'especes']);
        $this->assertSame(2, Paiement::count());

        // Mauvaise confirmation : rien n'est effacé
        $this->artisan('salle:effacer-donnees')->expectsQuestion('Tapez EFFACER pour confirmer', 'non')->assertFailed();
        $this->assertSame(2, Paiement::count());

        $this->artisan('salle:effacer-donnees')->expectsQuestion('Tapez EFFACER pour confirmer', 'EFFACER')->assertSuccessful();

        $this->assertSame([0, 0, 0, 0], [Client::withTrashed()->count(), Paiement::count(), \App\Models\Abonnement::count(), \App\Models\Passage::count()]);
        $this->assertSame(2, User::count());
        $this->assertGreaterThan(0, Formule::count());
        // Le membre de test est retiré de la pointeuse
        $this->assertSame(['action' => 'supprimer', 'numero' => '1'], json_decode($pointeuse->commandes()->sole()->commande, true));
    }
}
