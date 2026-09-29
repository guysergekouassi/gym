<?php

namespace Tests\Feature;

use App\Models\Abonnement;
use App\Models\Caisse;
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
        $this->actingAs($this->caissiere)->get('/dashboard')->assertForbidden()
            ->assertSee('Retourner à mon espace')->assertSee('Se déconnecter');
    }

    public function test_page_de_connexion_quand_on_est_deja_connecte(): void
    {
        // Déjà connecté : /login renvoie vers « / », qui aiguille selon le rôle (jamais vers une page interdite)
        $this->actingAs($this->caissiere)->get('/login')->assertRedirect('/');
        $this->get('/')->assertRedirect(route('caisse.index'));

        $this->actingAs($this->admin)->get('/login')->assertRedirect('/');
        $this->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_toutes_les_pages_saffichent(): void
    {
        $client = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Kouassi', 'prenoms' => 'Guy', 'empreinte_id' => '1']);
        $this->scanner('1');
        $this->scanner('404');

        $this->actingAs($this->caissiere)->post('/caisse/abonnement', [
            'client_id' => $client->id,
            'formule_id' => Formule::where('nom', 'Mensuel')->value('id'),
            'mode' => 'especes',
        ]);
        $paiement = Paiement::firstOrFail();
        $this->scanner('1');
        $caisse = Caisse::firstOrFail();

        $communes = ['/clients', '/clients/create', "/clients/{$client->id}", "/clients/{$client->id}/edit",
            '/passages', '/passages?vue=client', '/passages?filtre=refuse', '/passages?date='.today()->subDay()->toDateString(),
            '/accueil', '/accueil/dernier', "/recus/{$paiement->numero_recu}"];

        foreach (['/caisse', "/caisse?client_id={$client->id}", ...$communes] as $url) {
            $this->get($url)->assertOk();
        }

        $this->actingAs($this->admin);
        foreach (['/dashboard', '/dashboard?du='.today()->subDays(7)->toDateString(), '/admin/caisses', '/admin/caisses/create',
            "/admin/caisses/{$caisse->id}", "/admin/caisses/{$caisse->id}/edit", '/admin/caissiers', '/admin/caissiers/create',
            "/admin/caissiers/{$this->caissiere->id}/edit", ...$communes] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_les_espaces_admin_et_caisse_sont_separes(): void
    {
        $this->actingAs($this->admin)->get('/caisse')->assertForbidden();
        $this->actingAs($this->admin)->post('/caisse/journalier', ['montant' => 2000, 'mode' => 'especes'])->assertForbidden();

        $this->actingAs($this->caissiere);
        foreach (['/dashboard', '/admin/caisses', '/admin/caissiers'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_une_caissiere_sans_caisse_active_ne_peut_pas_encaisser(): void
    {
        $this->caissiere->caisse->update(['actif' => false]);

        $this->actingAs($this->caissiere)->get('/caisse')->assertForbidden()->assertSee('Aucune caisse active');
        $this->post('/caisse/journalier', ['montant' => 2000, 'mode' => 'especes'])->assertForbidden();
        $this->assertSame(0, Paiement::count());
    }

    public function test_ladmin_cree_plusieurs_caisses_et_leurs_caissieres(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/caisses', ['nom' => 'Caisse bar', 'emplacement' => 'Étage', 'actif' => '1'])
            ->assertRedirect(route('admin.caisses.index'));
        $bar = Caisse::where('nom', 'Caisse bar')->firstOrFail();
        $this->post('/admin/caisses', ['nom' => 'Caisse bar'])->assertSessionHasErrors('nom');

        $this->post('/admin/caissiers', [
            'name' => 'Mariam', 'email' => 'mariam@gymflow.local', 'caisse_id' => $bar->id, 'actif' => '1',
            'password' => 'MotDePasse!1', 'password_confirmation' => 'MotDePasse!1',
        ])->assertRedirect(route('admin.caissiers.index'));

        $mariam = User::where('email', 'mariam@gymflow.local')->firstOrFail();
        $this->assertTrue($mariam->isCaissier());
        $this->assertSame($bar->id, $mariam->caisse_id);

        // Chaque paiement est rattaché à la caisse de la caissière
        $this->actingAs($mariam)->post('/caisse/journalier', ['montant' => 2000, 'mode' => 'wave'])->assertRedirect();
        $this->actingAs($this->caissiere)->post('/caisse/journalier', ['montant' => 2000, 'mode' => 'especes'])->assertRedirect();
        $this->assertSame(1, Paiement::where('caisse_id', $bar->id)->count());
        $this->assertSame(1, Paiement::where('caisse_id', $this->caissiere->caisse_id)->count());

        $this->actingAs($this->admin)->get("/admin/caisses/{$bar->id}")->assertOk()
            ->assertViewHas('resume', fn ($r) => $r['total'] === 2000 && $r['electronique'] === 2000 && $r['especes'] === 0);

        // Désactiver le compte bloque la connexion
        $this->put("/admin/caissiers/{$mariam->id}", [
            'name' => 'Mariam', 'email' => 'mariam@gymflow.local', 'caisse_id' => $bar->id,
        ])->assertRedirect();
        $this->assertFalse($mariam->fresh()->actif);
        $this->assertTrue(password_verify('MotDePasse!1', $mariam->fresh()->password));
        $this->post('/logout');
        $this->post('/login', ['email' => 'mariam@gymflow.local', 'password' => 'MotDePasse!1'])->assertSessionHasErrors('email');
    }

    public function test_les_erreurs_de_saisie_sont_en_francais(): void
    {
        Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Existant', 'telephone' => '0700000009']);

        $this->actingAs($this->caissiere)->from('/clients/create')->post('/clients', [
            'type' => Client::TYPE_ABONNE,
            'nom' => '',
            'telephone' => '0700000009',
            'date_naissance' => today()->toDateString(),
        ])->assertRedirect('/clients/create')->assertSessionHasErrors([
            'nom' => 'Le champ nom est obligatoire.',
            'telephone' => 'Ce numéro de téléphone est déjà attribué à un autre client.',
            'date_naissance' => "La date de naissance doit être antérieure à aujourd'hui.",
        ]);
    }

    public function test_passages_du_jour_et_refus_a_regulariser(): void
    {
        $client = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Diallo', 'prenoms' => 'Fatou', 'empreinte_id' => '5']);
        $this->scanner('5')->assertJson(['autorise' => false]);
        $this->scanner('77')->assertJson(['motif' => 'empreinte_inconnue']);

        $this->actingAs($this->caissiere)->get('/caisse')->assertOk()
            ->assertViewHas('aRegulariser', fn ($refus) => $refus->count() === 2)
            ->assertSee('Diallo Fatou')->assertSee('Empreinte n° 77');

        $this->get('/passages?filtre=refuse')->assertOk()->assertSee('Diallo Fatou')
            ->assertViewHas('stats', fn ($s) => $s['refus'] === 2 && $s['entrees'] === 0);

        // Après renouvellement, le refus n'est plus à régulariser
        $this->post('/caisse/abonnement', [
            'client_id' => $client->id, 'formule_id' => Formule::where('nom', 'Mensuel')->value('id'), 'mode' => 'especes',
        ]);
        $this->scanner('5')->assertJson(['autorise' => true]);

        $this->get('/caisse')->assertViewHas('aRegulariser', fn ($refus) => $refus->count() === 1);
        $this->get('/passages?vue=client')->assertOk()
            ->assertViewHas('parClient', fn ($lignes) => $lignes->count() === 2)
            ->assertViewHas('venues7j', fn ($v) => (int) $v[$client->id] === 1);
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
            'nom' => 'Traoré', 'telephone' => '0700000001', 'montant' => 2000, 'mode' => 'especes',
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
            'client_id' => $client->id, 'montant' => 2000, 'mode' => 'especes',
        ]);

        $this->scanner('9')->assertJson(['autorise' => true]);
    }

    public function test_empreinte_inconnue_et_token_invalide(): void
    {
        $this->scanner('999')->assertJson(['autorise' => false, 'motif' => 'empreinte_inconnue']);
        $this->withToken('mauvais')->postJson('/api/pointage/empreinte', ['empreinte_id' => '1'])->assertUnauthorized();
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
