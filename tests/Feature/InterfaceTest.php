<?php

namespace Tests\Feature;

use App\Models\Abonnement;
use App\Models\Client;
use App\Models\Formule;
use App\Models\Paiement;
use App\Models\Parametre;
use App\Models\Passage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterfaceTest extends TestCase
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

    public function test_ticket_de_groupe_cree_une_entree_par_personne(): void
    {
        Parametre::definir(Parametre::TARIF_JOURNALIER, 2000);

        $this->actingAs($this->caissiere)->post('/caisse/journalier', ['mode' => 'especes', 'quantite' => 3, 'nom' => 'Groupe'])
            ->assertRedirect();

        $paiement = Paiement::firstOrFail();
        $this->assertSame(6000, $paiement->montant);
        $this->assertSame(3, $paiement->quantite);
        $this->assertSame(3, Passage::where('paiement_id', $paiement->id)->count());
        $this->get("/recus/{$paiement->numero_recu}")->assertSee('Entrée journalière × 3');

        $this->post('/caisse/journalier', ['mode' => 'especes', 'quantite' => 50])->assertSessionHasErrors('quantite');
    }

    public function test_tableau_de_bord_caissiere_ne_montre_que_sa_caisse(): void
    {
        $collegue = User::create(['name' => 'Collègue', 'email' => 'c2@x.ci', 'password' => 'Secret12345', 'role' => User::ROLE_CAISSIER]);
        $this->actingAs($collegue)->post('/caisse/journalier', ['mode' => 'especes']);
        $this->actingAs($this->caissiere)->post('/caisse/journalier', ['mode' => 'wave']);

        $this->get('/ma-journee')->assertOk()
            ->assertViewHas('aujourdhui', fn ($j) => $j['tickets'] === 1)
            ->assertViewHas('transactions', fn ($t) => $t->count() === 1 && $t->first()->mode === 'wave');
    }

    public function test_onglet_renouvellement_liste_les_fins_proches(): void
    {
        $formule = Formule::firstOrFail();
        $bientot = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Bientôt']);
        $loin = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Loin']);
        foreach ([[$bientot, 3], [$loin, 60]] as [$c, $jours]) {
            Abonnement::create([
                'client_id' => $c->id, 'formule_id' => $formule->id, 'date_debut' => today()->subDays(20),
                'date_fin' => today()->addDays($jours), 'montant' => 1, 'statut' => Abonnement::STATUT_ACTIF,
            ]);
        }

        $this->actingAs($this->caissiere)->get('/caisse?onglet=renouvellement')->assertOk()
            ->assertViewHas('onglet', 'renouvellement')
            ->assertViewHas('aRenouveler', fn ($l) => $l->pluck('nom')->all() === ['Bientôt']);

        // La cloche signale aussi l'échéance
        $this->get('/clients')->assertSee('Abonnements qui expirent bientôt');
    }

    public function test_nouveau_client_depuis_la_fenetre_puis_abonnement(): void
    {
        $this->actingAs($this->caissiere)->post('/clients', [
            '_modale' => 'nouveau', 'type' => Client::TYPE_ABONNE, 'nom' => 'Aka', 'empreinte_id' => '3', 'abonner' => '1',
        ])->assertRedirect(route('caisse.index', ['client_id' => Client::where('nom', 'Aka')->value('id')]));

        // Erreur de saisie : retour à la liste avec la fenêtre rouverte
        $this->from('/clients')->post('/clients', ['_modale' => 'nouveau', 'type' => Client::TYPE_ABONNE, 'nom' => '', 'empreinte_id' => '3'])
            ->assertRedirect('/clients')->assertSessionHasErrors(['nom', 'empreinte_id']);
        $this->assertSame('nouveau', session()->getOldInput('_modale'));

        // ?nouveau=1 (lien « Créer le client » de la caisse) ouvre la fenêtre d'office
        $this->get('/clients?nouveau=1')->assertSee('data-ouvrir', false);
        $this->get('/clients')->assertDontSee('data-ouvrir', false);
    }

    public function test_tableau_de_bord_admin_complet(): void
    {
        $this->actingAs($this->caissiere)->post('/caisse/journalier', ['mode' => 'especes']);

        $this->actingAs($this->admin)->get('/dashboard')->assertOk()
            ->assertViewHas('serie', fn ($r) => count($r['etiquettes']) === 7 && end($r['journalier']) > 0)
            ->assertViewHas('repartition')
            ->assertSee('Évolution des revenus')
            ->assertSee('Répartition des clients');
    }

    public function test_liste_deroulante_des_clients_et_date_dadhesion(): void
    {
        foreach (['Zadi', 'Aka', 'Bamba'] as $nom) {
            Client::create(['type' => Client::TYPE_ABONNE, 'nom' => $nom]);
        }

        // Liste ouverte sans recherche : clients par ordre alphabétique
        $this->actingAs($this->caissiere)->getJson('/caisse/clients')->assertOk()
            ->assertJsonPath('0.nom', 'Aka')->assertJsonCount(3);
        $this->getJson('/caisse/clients?q=bam')->assertJsonCount(1)->assertJsonPath('0.nom', 'Bamba');

        $this->post('/clients', ['type' => Client::TYPE_ABONNE, 'nom' => 'Nouveau'])->assertSessionHasNoErrors();
        $this->assertTrue(Client::where('nom', 'Nouveau')->firstOrFail()->date_adhesion->isToday());

        $this->post('/clients', ['type' => Client::TYPE_ABONNE, 'nom' => 'Ancien', 'date_adhesion' => '2024-05-02'])->assertSessionHasNoErrors();
        $ancien = Client::where('nom', 'Ancien')->firstOrFail();
        $this->assertSame('2024-05-02', $ancien->date_adhesion->toDateString());
        $this->get("/clients/{$ancien->id}")->assertSee('Membre depuis le 02/05/2024');

        $this->post('/clients', ['type' => Client::TYPE_ABONNE, 'nom' => 'Futur', 'date_adhesion' => '2099-01-01'])
            ->assertSessionHasErrors('date_adhesion');
    }
}
