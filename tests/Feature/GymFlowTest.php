<?php

namespace Tests\Feature;

use App\Models\Abonnement;
use App\Models\Client;
use App\Models\Formule;
use App\Models\Lecteur;
use App\Models\Paiement;
use App\Models\Passage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GymFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $caissiere;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();
        $this->caissiere = User::where('role', User::ROLE_CAISSIER)->firstOrFail();
        // Les comptes du seeder ont un mot de passe provisoire : on le considère déjà changé
        User::query()->update(['doit_changer_mdp' => false]);
        $this->admin->refresh();
        $this->caissiere->refresh();

        Lecteur::query()->delete();
        [, $this->token] = Lecteur::creerAvecToken('Test');
    }

    private function scanner(string $empreinteId)
    {
        return $this->withToken($this->token)->postJson('/api/pointage/empreinte', ['empreinte_id' => $empreinteId]);
    }

    public function test_connexion_et_redirection_selon_le_role(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk();

        $this->post('/login', ['email' => 'admin@gymflow.local', 'password' => 'ChangeMoi!2026']);
        $this->assertAuthenticatedAs($this->admin);
        $this->get('/')->assertRedirect(route('dashboard'));
        $this->post('/logout');

        $this->post('/login', ['email' => 'caisse@gymflow.local', 'password' => 'ChangeMoi!2026'])
            ->assertRedirect(route('caisse.index'));
        $this->get('/')->assertRedirect(route('caisse.index'));

        $this->post('/logout');
        $this->post('/login', ['email' => 'admin@gymflow.local', 'password' => 'mauvais'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_la_caissiere_na_pas_acces_au_tableau_de_bord(): void
    {
        $this->actingAs($this->caissiere)->get('/dashboard')->assertForbidden();
    }

    public function test_toutes_les_pages_saffichent(): void
    {
        $client = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Kouassi', 'prenoms' => 'Guy', 'empreinte_id' => '1']);

        $this->actingAs($this->admin)->post('/caisse/abonnement', [
            'client_id' => $client->id,
            'formule_id' => Formule::where('nom', 'Mensuel')->value('id'),
            'mode' => 'especes',
        ]);
        $paiement = Paiement::firstOrFail();

        foreach (['/dashboard', '/caisse', '/caisse?onglet=abonnement', '/clients', '/clients?statut=expire&q=50%_', '/clients/create',
            "/clients/{$client->id}", "/clients/{$client->id}/edit", '/accueil', '/accueil/dernier',
            "/recus/{$paiement->numero_recu}", '/mot-de-passe', '/admin/paiements', '/admin/formules',
            '/admin/utilisateurs', '/admin/utilisateurs/create', "/admin/utilisateurs/{$this->caissiere->id}/edit",
            '/admin/pointeuses'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_abonnement_puis_scan_autorise(): void
    {
        $client = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Yao', 'empreinte_id' => '7']);

        $this->scanner('7')->assertOk()->assertJson(['autorise' => false, 'motif' => 'abonnement_expire']);

        $this->actingAs($this->caissiere)->post('/caisse/abonnement', [
            'client_id' => $client->id,
            'formule_id' => Formule::where('nom', 'Mensuel')->value('id'),
            'mode' => 'wave',
        ])->assertRedirect();

        $abonnement = Abonnement::firstOrFail();
        $this->assertSame(today()->toDateString(), $abonnement->date_debut->toDateString());
        $this->assertSame(today()->addDays(29)->toDateString(), $abonnement->date_fin->toDateString());

        $this->scanner('7')->assertOk()->assertJson([
            'autorise' => true,
            'client' => ['nom' => 'Yao'],
            'abonnement' => ['formule' => 'Mensuel', 'jours_restants' => 29],
        ]);

        // Anti-doublon : un second scan immédiat ne crée pas de passage
        $this->scanner('7')->assertOk();
        $this->assertSame(1, Passage::where('statut', Passage::STATUT_AUTORISE)->count());
    }

    public function test_renouvellement_anticipe_ne_perd_aucun_jour(): void
    {
        $client = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Koffi']);
        $mensuel = Formule::where('nom', 'Mensuel')->value('id');

        $this->actingAs($this->caissiere);
        $this->post('/caisse/abonnement', ['client_id' => $client->id, 'formule_id' => $mensuel, 'mode' => 'especes']);
        $this->post('/caisse/abonnement', ['client_id' => $client->id, 'formule_id' => $mensuel, 'mode' => 'especes']);

        $second = Abonnement::latest('id')->firstOrFail();
        $this->assertTrue($second->est_renouvellement);
        $this->assertSame(today()->addDays(30)->toDateString(), $second->date_debut->toDateString());
    }

    public function test_journalier_paye_a_la_caisse_et_recu(): void
    {
        $this->actingAs($this->caissiere)->post('/caisse/journalier', [
            'nom' => 'Traoré', 'telephone' => '0700000001', 'mode' => 'especes',
        ])->assertRedirect();

        $paiement = Paiement::firstOrFail();
        $this->assertMatchesRegularExpression('/^R\d{8}-\d{6}$/', $paiement->numero_recu);
        $this->assertSame(Client::TYPE_JOURNALIER, $paiement->client->type);
        $this->assertDatabaseHas('passages', ['paiement_id' => $paiement->id, 'statut' => 'autorise', 'methode' => 'caisse']);

        $this->get("/recus/{$paiement->numero_recu}")->assertOk()->assertSee($paiement->numero_recu)->assertSee('2 000');
    }

    public function test_journalier_enrole_doit_payer_avant_de_scanner(): void
    {
        $client = Client::create(['type' => Client::TYPE_JOURNALIER, 'nom' => 'Bamba', 'empreinte_id' => '9']);

        $this->scanner('9')->assertJson(['autorise' => false, 'motif' => 'paiement_requis']);

        $this->actingAs($this->caissiere)->post('/caisse/journalier', [
            'client_id' => $client->id, 'mode' => 'especes',
        ]);

        $this->scanner('9')->assertJson(['autorise' => true]);
    }

    public function test_empreinte_inconnue_et_token_invalide(): void
    {
        $this->scanner('999')->assertJson(['autorise' => false, 'motif' => 'empreinte_inconnue']);
        $this->withToken('mauvais')->postJson('/api/pointage/empreinte', ['empreinte_id' => '1'])->assertUnauthorized();
        $this->postJson('/api/pointage/empreinte', ['empreinte_id' => '1'])->assertUnauthorized();
        $this->scanner("1' OR '1'='1")->assertUnprocessable();
    }

    public function test_kpi_actifs_moins_actifs_et_renouvellements(): void
    {
        $mensuel = Formule::where('nom', 'Mensuel')->firstOrFail();
        $nouvelAbonnement = fn (Client $c, $debut, bool $renouv = false) => Abonnement::create([
            'client_id' => $c->id, 'formule_id' => $mensuel->id, 'date_debut' => $debut,
            'date_fin' => $debut->copy()->addDays(29), 'montant' => $mensuel->prix,
            'statut' => Abonnement::STATUT_ACTIF, 'est_renouvellement' => $renouv,
        ]);

        $assidu = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Assidu', 'empreinte_id' => '1']);
        $absent = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Absent']);
        $fidele = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Fidèle']);

        $nouvelAbonnement($assidu, today()->subDays(5));
        $nouvelAbonnement($absent, today()->subDays(20));
        $nouvelAbonnement($fidele, today()->subDays(40));
        $nouvelAbonnement($fidele, today()->subDays(10), true);

        $this->scanner('1');

        $this->actingAs($this->admin)->get('/dashboard')->assertOk()
            ->assertViewHas('actifs', fn ($c) => $c->pluck('nom')->all() === ['Assidu'])
            ->assertViewHas('moinsActifs', fn ($c) => $c->pluck('nom')->sort()->values()->all() === ['Absent', 'Fidèle'])
            ->assertViewHas('renouvellement', fn ($r) => $r['renouveles'] === 1 && $r['taux'] === 100.0)
            ->assertViewHas('abonnementsEnCours', 3);
    }
}
