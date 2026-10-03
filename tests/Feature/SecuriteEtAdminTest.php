<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PaiementController;
use App\Models\Abonnement;
use App\Models\Client;
use App\Models\Formule;
use App\Models\Paiement;
use App\Models\Parametre;
use App\Models\Passage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecuriteEtAdminTest extends TestCase
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

    public function test_premiere_connexion_impose_un_nouveau_mot_de_passe(): void
    {
        $this->caissiere->update(['doit_changer_mdp' => true]);

        $this->post('/login', ['email' => 'caisse@gymflow.local', 'password' => 'ChangeMoi!2026'])
            ->assertRedirect(route('mot-de-passe.edit'));
        $this->get('/caisse')->assertRedirect(route('mot-de-passe.edit'));

        // Mot de passe trop faible refusé
        $this->put('/mot-de-passe', [
            'current_password' => 'ChangeMoi!2026', 'password' => 'faible', 'password_confirmation' => 'faible',
        ])->assertSessionHasErrors('password');

        $this->put('/mot-de-passe', [
            'current_password' => 'ChangeMoi!2026', 'password' => 'NouveauSecret42', 'password_confirmation' => 'NouveauSecret42',
        ])->assertRedirect('/');

        $this->assertFalse($this->caissiere->fresh()->doit_changer_mdp);
        $this->get('/caisse')->assertOk();
    }

    public function test_verrouillage_apres_cinq_echecs_de_connexion(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'admin@gymflow.local', 'password' => 'mauvais'.$i]);
        }

        // Même le bon mot de passe est refusé pendant le blocage
        $this->post('/login', ['email' => 'admin@gymflow.local', 'password' => 'ChangeMoi!2026'])
            ->assertSessionHasErrors(['email' => 'Trop de tentatives. Réessayez dans 5 minute(s).']);
        $this->assertGuest();
    }

    public function test_compte_desactive_ne_peut_plus_rien_faire(): void
    {
        $this->post('/login', ['email' => 'caisse@gymflow.local', 'password' => 'ChangeMoi!2026']);
        $this->assertAuthenticated();

        $this->caissiere->update(['actif' => false]);
        $this->app['auth']->forgetGuards(); // nouvelle requête = utilisateur relu en base
        $this->get('/caisse')->assertForbidden();
    }

    public function test_la_caissiere_na_acces_a_aucune_page_admin(): void
    {
        $this->actingAs($this->caissiere);
        $paiement = $this->encaisserPassage();

        foreach (['/dashboard', '/admin/paiements', '/admin/paiements/export', '/admin/formules',
            '/admin/utilisateurs', '/admin/utilisateurs/create', '/admin/lecteurs'] as $url) {
            $this->get($url)->assertForbidden();
        }

        $this->post("/admin/paiements/{$paiement->numero_recu}/annuler", ['motif' => 'je veux annuler'])->assertForbidden();
        $this->put('/admin/tarif-journalier', ['tarif_journalier' => 0])->assertForbidden();
        $this->post('/admin/utilisateurs', [
            'name' => 'Pirate', 'email' => 'p@x.ci', 'role' => 'admin', 'password' => 'Azerty12345', 'password_confirmation' => 'Azerty12345',
        ])->assertForbidden();
        $this->put("/admin/utilisateurs/{$this->caissiere->id}", ['name' => 'X', 'email' => 'caisse@gymflow.local', 'role' => 'admin', 'actif' => 1])
            ->assertForbidden();

        $this->assertSame(User::ROLE_CAISSIER, $this->caissiere->fresh()->role);
        $this->assertSame(0, Paiement::whereNotNull('annule_le')->count());
    }

    public function test_le_montant_du_passage_est_impose_par_le_serveur(): void
    {
        Parametre::definir(Parametre::TARIF_JOURNALIER, 2500);

        $this->actingAs($this->caissiere)->post('/caisse/journalier', ['mode' => 'especes', 'montant' => 0]);

        $this->assertSame(2500, Paiement::firstOrFail()->montant);
    }

    public function test_annulation_dun_abonnement_par_ladmin(): void
    {
        $client = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Konan', 'badge_id' => '42']);
        $this->actingAs($this->caissiere)->post('/caisse/abonnement', [
            'client_id' => $client->id, 'formule_id' => Formule::where('nom', 'Mensuel')->value('id'), 'mode' => 'especes',
        ]);
        $paiement = Paiement::firstOrFail();

        $this->actingAs($this->admin)
            ->post("/admin/paiements/{$paiement->numero_recu}/annuler", ['motif' => 'Mauvaise formule'])
            ->assertSessionHas('succes');

        $paiement->refresh();
        $this->assertTrue($paiement->estAnnule());
        $this->assertSame($this->admin->id, $paiement->annule_par);
        $this->assertSame(Abonnement::STATUT_ANNULE, $paiement->abonnement->statut);
        $this->assertNull($client->fresh()->finDesDroits());

        // Le badge est refusé et la recette du jour exclut le ticket annulé
        $this->post('/accueil/scan', ['badge_id' => '42'])->assertJson(['autorise' => false]);
        $this->get('/dashboard')->assertViewHas('jour', fn ($j) => $j['recette_abonnements'] === 0);

        // Double annulation impossible
        $this->post("/admin/paiements/{$paiement->numero_recu}/annuler", ['motif' => 'Encore une fois'])
            ->assertSessionHas('erreur');
    }

    public function test_scan_badge_usb_depuis_lecran_daccueil(): void
    {
        $client = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Aka', 'badge_id' => '0012345678']);

        $this->actingAs($this->caissiere)->postJson('/accueil/scan', ['badge_id' => '0012345678'])
            ->assertOk()->assertJson(['autorise' => false, 'message' => 'Abonnement expiré ou inexistant']);

        Abonnement::create([
            'client_id' => $client->id, 'formule_id' => Formule::value('id'), 'date_debut' => today(),
            'date_fin' => today()->addDays(29), 'montant' => 1, 'statut' => Abonnement::STATUT_ACTIF,
        ]);

        $this->postJson('/accueil/scan', ['badge_id' => '0012345678'])
            ->assertOk()->assertJson(['autorise' => true, 'client' => ['nom' => 'Aka']]);
        $this->assertDatabaseHas('passages', ['client_id' => $client->id, 'methode' => Passage::METHODE_BADGE, 'user_id' => $this->caissiere->id]);

        $this->postJson('/accueil/scan', ['badge_id' => '<script>'])->assertUnprocessable();
    }

    public function test_entetes_de_securite_presents(): void
    {
        $reponse = $this->get('/login');

        $reponse->assertHeader('X-Frame-Options', 'DENY');
        $reponse->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("frame-ancestors 'none'", $reponse->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString('unsafe-eval', $reponse->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString('cdn.tailwindcss.com', $reponse->getContent());

        $this->actingAs($this->caissiere)->get('/caisse')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_recherche_echappe_les_jokers_like(): void
    {
        Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Kouame']);
        Client::create(['type' => Client::TYPE_ABONNE, 'nom' => '100%_Fitness']);

        // Sans échappement, "%_" et "__" correspondraient à tous les clients
        $this->actingAs($this->caissiere)->getJson('/caisse/clients?q=%25_')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.nom', '100%_Fitness');
        $this->getJson('/caisse/clients?q=__')->assertJsonCount(0);
    }

    public function test_export_csv_neutralise_les_formules_excel(): void
    {
        $this->assertSame("'=HYPERLINK(\"x\")", PaiementController::celluleSure('=HYPERLINK("x")'));
        $this->assertSame("'+33", PaiementController::celluleSure('+33'));
        $this->assertSame('Kouassi', PaiementController::celluleSure('Kouassi'));

        $this->actingAs($this->caissiere);
        Client::create(['type' => Client::TYPE_JOURNALIER, 'nom' => '=CMD()', 'telephone' => '0700000099']);
        $this->post('/caisse/journalier', ['telephone' => '0700000099', 'mode' => 'especes']);

        $csv = $this->actingAs($this->admin)->get('/admin/paiements/export')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=CMD()", $csv);
    }

    public function test_upload_photo_refuse_les_fichiers_non_images(): void
    {
        Storage::fake('public');
        $this->actingAs($this->caissiere);

        $this->post('/clients', [
            'type' => Client::TYPE_ABONNE, 'nom' => 'Test',
            'photo' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors('photo');

        $this->post('/clients', [
            'type' => Client::TYPE_ABONNE, 'nom' => 'Test',
            'photo' => UploadedFile::fake()->createWithContent('photo.svg', '<svg onload="alert(1)"></svg>'),
        ])->assertSessionHasErrors('photo');

        $this->post('/clients', [
            'type' => Client::TYPE_ABONNE, 'nom' => 'Test', 'photo' => UploadedFile::fake()->image('moi.jpg', 200, 200),
        ])->assertSessionHasNoErrors();
        $chemin = Client::firstOrFail()->photo_path;
        $this->assertMatchesRegularExpression('#^clients/[a-f0-9]{40}\.jpg$#', $chemin);
        Storage::disk('public')->assertExists($chemin);
    }

    public function test_gestion_des_comptes_et_garde_fous(): void
    {
        $this->actingAs($this->admin)->post('/admin/utilisateurs', [
            'name' => 'Awa', 'email' => 'awa@gymflow.local', 'role' => User::ROLE_CAISSIER,
            'password' => 'Provisoire2026', 'password_confirmation' => 'Provisoire2026',
        ])->assertRedirect(route('admin.utilisateurs.index'));
        $this->assertTrue(User::where('email', 'awa@gymflow.local')->firstOrFail()->doit_changer_mdp);

        // L'admin ne peut pas se désactiver ni se rétrograder lui-même
        $this->put("/admin/utilisateurs/{$this->admin->id}", [
            'name' => 'Admin', 'email' => 'admin@gymflow.local', 'role' => User::ROLE_CAISSIER, 'actif' => 1,
        ])->assertSessionHas('erreur');
        $this->assertTrue($this->admin->fresh()->isAdmin());
    }

    public function test_archiver_un_client_libere_son_badge(): void
    {
        $client = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Parti', 'badge_id' => '77']);

        $this->actingAs($this->admin)->delete("/clients/{$client->id}")->assertRedirect();

        $this->post('/clients', ['type' => Client::TYPE_ABONNE, 'nom' => 'Nouveau', 'badge_id' => '77'])->assertSessionHasNoErrors();
        $this->actingAs($this->caissiere)->post('/caisse/abonnement', [
            'client_id' => $client->id, 'formule_id' => Formule::value('id'), 'mode' => 'especes',
        ])->assertSessionHasErrors('client_id');
    }

    private function encaisserPassage(): Paiement
    {
        $this->post('/caisse/journalier', ['mode' => 'especes']);

        return Paiement::latest('id')->firstOrFail();
    }
}
