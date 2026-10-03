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
}
