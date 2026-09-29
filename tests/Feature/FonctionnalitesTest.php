<?php

namespace Tests\Feature;

use App\Models\Abonnement;
use App\Models\Caisse;
use App\Models\Client;
use App\Models\Coach;
use App\Models\CodePromo;
use App\Models\Cours;
use App\Models\Formule;
use App\Models\Journal;
use App\Models\Lecteur;
use App\Models\Message;
use App\Models\PackCoaching;
use App\Models\Paiement;
use App\Models\PaiementEnLigne;
use App\Models\Passage;
use App\Models\Produit;
use App\Models\Prospect;
use App\Models\Reservation;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FonctionnalitesTest extends TestCase
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

    private function formule(string $nom): Formule
    {
        return Formule::where('nom', $nom)->firstOrFail();
    }

    private function abonner(Client $client, string $formule = 'Mensuel', array $extra = [])
    {
        return $this->actingAs($this->caissiere)->post('/caisse/abonnement', [
            'client_id' => $client->id, 'formule_id' => $this->formule($formule)->id, 'mode' => 'especes',
        ] + $extra);
    }

    private function scanner(string $empreinte)
    {
        return $this->withToken($this->token)->postJson('/api/pointage/empreinte', ['empreinte_id' => $empreinte]);
    }

    public function test_toutes_les_nouvelles_pages_saffichent(): void
    {
        $client = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Konan', 'prenoms' => 'Esther', 'telephone' => '0711523890', 'empreinte_id' => '14']);
        $coach = Coach::create(['nom' => 'Karim', 'commission_pct' => 30, 'actif' => true]);
        $cours = Cours::create(['nom' => 'Zumba', 'coach_id' => $coach->id, 'jour_semaine' => today()->isoWeekday(), 'heure' => '23:00', 'capacite' => 2, 'actif' => true]);
        $produit = Produit::create(['nom' => 'Eau 50 cl', 'prix' => 300, 'stock' => 10, 'seuil_alerte' => 5, 'actif' => true]);
        $promo = CodePromo::create(['code' => 'RENTREE', 'type' => 'pourcentage', 'valeur' => 10, 'actif' => true]);
        $prospect = Prospect::create(['nom' => 'Yapi Rodrigue', 'telephone' => '0102030405', 'statut' => 'nouveau']);
        $this->abonner($client);
        $client->mesures()->create(['date' => today()->subDays(30), 'poids' => 82]);
        $client->mesures()->create(['date' => today(), 'poids' => 79.5]);

        $communes = ['/a-faire', '/prospects', '/planning', "/planning/{$cours->id}/".today()->toDateString(), '/compte',
            "/clients/{$client->id}", '/clients/create?carte_id=0012', '/recherche?q=kon'];

        $this->actingAs($this->caissiere);
        foreach (['/caisse', '/caisse/cloture', ...$communes] as $url) {
            $this->get($url)->assertOk();
        }

        $this->actingAs($this->admin);
        foreach (['/dashboard', '/admin/formules', '/admin/formules/create', "/admin/formules/{$this->formule('Mensuel')->id}/edit",
            '/admin/promos', '/admin/promos/create', "/admin/promos/{$promo->id}/edit", '/admin/coachs', '/admin/coachs/create',
            "/admin/coachs/{$coach->id}/edit", '/admin/commissions', '/admin/cours', '/admin/cours/create', "/admin/cours/{$cours->id}/edit",
            '/admin/produits', '/admin/produits/create', "/admin/produits/{$produit->id}/edit", '/admin/salles', '/admin/salles/create',
            '/admin/lecteurs', '/admin/journal', '/admin/exports', '/admin/rapports', '/admin/campagnes', '/admin/campagnes/create',
            ...$communes] as $url) {
            $this->get($url)->assertOk();
        }

        $this->post('/logout');
        $this->get('/login')->assertOk()->assertSee('Heureux de vous revoir');
        $this->get('/mot-de-passe-oublie')->assertOk();
    }

    public function test_annuler_un_recu_le_retire_des_recettes(): void
    {
        $client = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Traoré']);
        $this->abonner($client);
        $paiement = Paiement::firstOrFail();

        $this->post("/recus/{$paiement->numero_recu}/annuler", ['motif' => 'erreur de formule'])->assertRedirect();

        $paiement->refresh();
        $this->assertTrue($paiement->estAnnule());
        $this->assertSame(Abonnement::STATUT_ANNULE, $paiement->abonnement->statut);
        $this->assertNull($client->fresh()->abonnementActif());
        $this->assertDatabaseHas('journal', ['action' => 'paiement.annule', 'user_id' => $this->caissiere->id]);

        $this->actingAs($this->admin)->get("/admin/caisses/{$paiement->caisse_id}")
            ->assertViewHas('resume', fn ($r) => $r['total'] === 0 && $r['annules']->count() === 1);
    }

    public function test_cloture_de_caisse_avec_ecart_et_blocage(): void
    {
        $this->actingAs($this->caissiere)->post('/caisse/journalier', ['montant' => 2000, 'mode' => 'especes']);
        $this->post('/caisse/journalier', ['montant' => 2000, 'mode' => 'wave']);

        // 5 000 de fond + 2 000 d'espèces attendus, 6 000 comptés : l'écart doit être justifié
        $this->post('/caisse/cloture', ['fond_caisse' => 5000, 'coupures' => [5000 => 1, 1000 => 1]])->assertSessionHasErrors('motif_ecart');
        $this->post('/caisse/cloture', ['fond_caisse' => 5000, 'coupures' => [5000 => 1, 1000 => 1], 'motif_ecart' => 'monnaie rendue'])->assertRedirect('/caisse');

        $cloture = Caisse::firstOrFail()->clotures()->firstOrFail();
        $this->assertSame(-1000, $cloture->ecart);
        $this->assertSame(2000, $cloture->electronique);

        $this->post('/caisse/journalier', ['montant' => 2000, 'mode' => 'especes'])->assertSessionHasErrors('caisse');
        $this->assertSame(2, Paiement::count());

        // Après clôture, la caissière ne peut plus annuler ; l'admin si
        $paiement = Paiement::firstOrFail();
        $this->post("/recus/{$paiement->numero_recu}/annuler", ['motif' => 'test'])->assertSessionHasErrors('motif');
        $this->actingAs($this->admin)->post("/recus/{$paiement->numero_recu}/annuler", ['motif' => 'test'])->assertSessionHasNoErrors();
    }

    public function test_carnet_dentrees_decompte_et_epuise(): void
    {
        $formule = Formule::create(['nom' => 'Carnet 2', 'type' => Formule::TYPE_CARNET, 'duree_jours' => 30, 'nb_entrees' => 2, 'prix' => 4000, 'actif' => true]);
        $client = Client::create(['type' => Client::TYPE_JOURNALIER, 'nom' => 'Bamba', 'empreinte_id' => '21']);
        $this->abonner($client, 'Carnet 2');
        $this->assertSame(Client::TYPE_ABONNE, $client->fresh()->type);

        $this->scanner('21')->assertJson(['autorise' => true, 'abonnement' => ['entrees_restantes' => 1]]);
        $this->travel(3)->minutes();
        $this->scanner('21')->assertJson(['autorise' => true]);
        $this->travel(3)->minutes();
        $this->scanner('21')->assertJson(['autorise' => false, 'motif' => 'carnet_epuise']);
        $this->assertSame(0, $client->abonnements()->first()->entrees_restantes);
        $this->assertNotNull($formule);
    }

    public function test_code_promo_et_frais_dinscription(): void
    {
        config(['salle.frais_inscription' => 5000]);
        CodePromo::create(['code' => 'RENTREE10', 'type' => 'pourcentage', 'valeur' => 10, 'utilisations_max' => 1, 'actif' => true]);
        $client = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Yao']);

        $this->abonner($client, 'Mensuel', ['code_promo' => 'rentree10', 'frais_inscription' => '1'])->assertRedirect();
        $paiement = Paiement::latest('id')->firstOrFail();
        $this->assertSame(15000 - 1500 + 5000, $paiement->montant);
        $this->assertSame(1500, $paiement->abonnement->remise);

        // Code épuisé, et plus de frais d'inscription au renouvellement
        $this->abonner($client, 'Mensuel', ['code_promo' => 'RENTREE10'])->assertSessionHasErrors('code_promo');
        $this->abonner($client, 'Mensuel', ['frais_inscription' => '1']);
        $this->assertSame(15000, Paiement::latest('id')->first()->montant);
    }

    public function test_gel_refuse_lacces_et_rend_les_jours(): void
    {
        $client = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Koné', 'empreinte_id' => '42']);
        $this->abonner($client);
        $finAvant = $client->finDesDroits();

        $this->post("/clients/{$client->id}/gels", ['du' => today()->toDateString(), 'jours' => 10, 'motif' => 'voyage'])->assertRedirect();
        $this->assertTrue($client->finDesDroits()->isSameDay($finAvant->copy()->addDays(10)));
        $this->scanner('42')->assertJson(['autorise' => false, 'motif' => 'abonnement_gele']);

        // Retour anticipé : aucun jour utilisé, le gel est annulé
        $gel = $client->gels()->firstOrFail();
        $this->post("/gels/{$gel->id}/terminer")->assertRedirect();
        $this->assertTrue($client->finDesDroits()->isSameDay($finAvant));
        $this->travel(3)->minutes();
        $this->scanner('42')->assertJson(['autorise' => true]);
    }

    public function test_pointage_par_carte_et_qr_code(): void
    {
        $client = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Ouattara', 'carte_id' => '0012774155']);
        $this->abonner($client);

        $this->withToken($this->token)->postJson('/api/pointage/carte', ['carte' => '0012774155'])->assertJson(['autorise' => true, 'ouvrir_porte' => true]);
        $this->travel(3)->minutes();
        $this->withToken($this->token)->postJson('/api/pointage/carte', ['carte' => strtolower($client->code_acces)])->assertJson(['autorise' => true]);
        $this->withToken($this->token)->postJson('/api/pointage/carte', ['carte' => 'INCONNU99'])->assertJson(['autorise' => false, 'motif' => 'carte_inconnue']);
        $this->assertSame(2, Passage::where('methode', Passage::METHODE_CARTE)->where('statut', 'autorise')->count());

        // Lecteur USB branché sur l'écran d'accueil
        $this->travel(3)->minutes();
        $this->actingAs($this->caissiere)->postJson('/accueil/badge', ['carte' => '0012774155'])->assertOk()->assertJson(['autorise' => true, 'client' => ['prenom' => 'Ouattara']]);
    }

    public function test_capture_de_lempreinte_ou_de_la_carte_a_lenrolement(): void
    {
        $depuis = now()->timestamp;
        $this->actingAs($this->caissiere)->getJson("/clients/capture?type=empreinte&depuis={$depuis}")->assertJson(['identifiant' => null]);

        // Le doigt vient d'être enrôlé sur l'appareil : son premier scan arrive « inconnu » avec le numéro attribué
        $this->scanner('125');
        $this->getJson("/clients/capture?type=empreinte&depuis={$depuis}")->assertJson(['identifiant' => '125']);
        $this->getJson("/clients/capture?type=carte&depuis={$depuis}")->assertJson(['identifiant' => null]);

        // Un scan plus ancien que l'ouverture de la fenêtre n'est pas repris
        $this->travel(1)->minutes();
        $this->getJson('/clients/capture?type=empreinte&depuis='.now()->timestamp)->assertJson(['identifiant' => null]);

        // Numéro déjà attribué à un client : on le signale
        Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Déjà', 'empreinte_id' => '125']);
        $this->getJson("/clients/capture?type=empreinte&depuis={$depuis}")->assertJson(['identifiant' => null, 'deja_attribue' => true]);
    }

    public function test_formulaires_envoyes_depuis_une_fenetre(): void
    {
        $this->actingAs($this->admin);
        $modale = ['X-Modal' => '1', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

        // Le formulaire se charge dans la fenêtre sans devenir la « page précédente »
        $this->get('/admin/salles');
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get('/admin/salles/create')->assertOk()->assertSee('form-card', false);

        // Erreur de saisie : 422 avec le détail par champ, la fenêtre reste ouverte
        $this->withHeaders($modale)->post('/admin/salles', ['nom' => ''])->assertStatus(422)->assertJsonValidationErrors('nom');

        // Succès : la redirection devient { redirect } et le message reste pour la page suivante
        $this->withHeaders($modale)->post('/admin/salles', ['nom' => 'GymFlow Riviera', 'actif' => '1'])
            ->assertOk()->assertJson(['redirect' => route('admin.salles.index')]);
        $this->assertDatabaseHas('salles', ['nom' => 'GymFlow Riviera']);
        $this->flushHeaders()->get('/admin/salles')->assertOk()->assertSee('Salle ajoutée.');

        // Sans l'en-tête, le comportement classique ne change pas
        $this->post('/admin/salles', ['nom' => 'Autre salle'])->assertRedirect(route('admin.salles.index'));
    }

    public function test_porte_ouverte_apres_un_acces_autorise(): void
    {
        config(['salle.porte.driver' => 'http', 'salle.porte.url' => 'http://relais.local/ouvrir']);
        Http::fake(['relais.local/*' => Http::response('OK')]);
        $client = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Aka', 'empreinte_id' => '8']);

        $this->scanner('8');
        Http::assertNothingSent();

        $this->abonner($client);
        $this->scanner('8');
        Http::assertSent(fn ($r) => $r->url() === 'http://relais.local/ouvrir');
    }

    public function test_vente_au_comptoir_stock_et_annulation(): void
    {
        $eau = Produit::create(['nom' => 'Eau', 'prix' => 300, 'stock' => 5, 'seuil_alerte' => 2, 'actif' => true]);
        $boisson = Produit::create(['nom' => 'Boisson énergétique', 'prix' => 1000, 'stock' => 1, 'seuil_alerte' => 2, 'actif' => true]);

        $this->actingAs($this->caissiere)->post('/caisse/vente', ['quantites' => [$eau->id => 3, $boisson->id => 2], 'mode' => 'especes'])
            ->assertSessionHasErrors('produits');

        $this->post('/caisse/vente', ['quantites' => [$eau->id => 3, $boisson->id => 1], 'mode' => 'orange_money'])->assertRedirect();
        $vente = Paiement::where('type', Paiement::TYPE_VENTE)->firstOrFail();
        $this->assertSame(1900, $vente->montant);
        $this->assertSame(2, $eau->fresh()->stock);
        $this->assertSame(0, $boisson->fresh()->stock);

        $this->post("/recus/{$vente->numero_recu}/annuler", ['motif' => 'client a changé d’avis']);
        $this->assertSame(5, $eau->fresh()->stock);
        $this->assertSame(1, $boisson->fresh()->stock);
    }

    public function test_coaching_seances_et_commissions(): void
    {
        $coach = Coach::create(['nom' => 'Karim', 'commission_pct' => 40, 'actif' => true]);
        $client = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Touré']);

        $this->actingAs($this->caissiere)->post('/caisse/coaching', [
            'client_id' => $client->id, 'formule_id' => $this->formule('Coaching 8 séances')->id, 'coach_id' => $coach->id, 'mode' => 'wave',
        ])->assertRedirect();

        $pack = PackCoaching::firstOrFail();
        $this->assertSame(8, $pack->seances_restantes);
        $this->post("/packs/{$pack->id}/seance", ['coach_id' => $coach->id])->assertRedirect();
        $this->post("/packs/{$pack->id}/seance", ['coach_id' => $coach->id]);
        $this->assertSame(6, $pack->fresh()->seances_restantes);

        // 2 séances × 7 500 F × 40 % = 6 000 F
        $this->actingAs($this->admin)->get('/admin/commissions')->assertOk()
            ->assertViewHas('parCoach', fn ($l) => $l->first()['commission'] === 6000 && $l->first()['seances'] === 2);
    }

    public function test_rappels_automatiques_sans_doublon(): void
    {
        $bientot = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Diallo', 'prenoms' => 'Fatou', 'telephone' => '0544901237']);
        $inactif = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Kouassi', 'prenoms' => 'Jean-Marc', 'telephone' => '0758214410']);
        $mensuel = $this->formule('Mensuel');
        Abonnement::create(['client_id' => $bientot->id, 'formule_id' => $mensuel->id, 'date_debut' => today()->subDays(26), 'date_fin' => today()->addDays(3), 'montant' => 15000, 'statut' => 'actif']);
        Abonnement::create(['client_id' => $inactif->id, 'formule_id' => $mensuel->id, 'date_debut' => today()->subDays(20), 'date_fin' => today()->addDays(9), 'montant' => 15000, 'statut' => 'actif']);
        Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Bamba', 'telephone' => '0790142671', 'date_naissance' => today()->subYears(30)]);

        $compte = app(MessageService::class)->genererRappels();
        $this->assertSame(1, $compte['expiration']);
        $this->assertSame(2, $compte['inactif']);
        $this->assertSame(1, $compte['anniversaire']);

        $message = Message::where('client_id', $bientot->id)->where('type', 'expiration')->firstOrFail();
        $this->assertStringContainsString('Fatou', $message->contenu);
        $this->assertStringContainsString(today()->addDays(3)->format('d/m/Y'), $message->contenu);
        $this->assertStringStartsWith('https://wa.me/2250544901237?text=', $message->lienWhatsapp());

        app(MessageService::class)->genererRappels();
        $this->assertSame(4, Message::count());

        $this->actingAs($this->caissiere)->get('/a-faire')->assertOk()->assertSee('Fatou');
        $this->post("/messages/{$message->id}/envoye")->assertRedirect();
        $this->assertSame('envoye', $message->fresh()->statut);
    }

    public function test_reservations_avec_liste_dattente(): void
    {
        $cours = Cours::create(['nom' => 'Cardio-boxe', 'jour_semaine' => today()->addDay()->isoWeekday(), 'heure' => '18:30', 'capacite' => 1, 'actif' => true]);
        $date = today()->addDay()->toDateString();
        $a = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'A']);
        $b = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'B']);

        $this->actingAs($this->caissiere)->post("/planning/{$cours->id}/{$date}/reservations", ['client_id' => $a->id]);
        $this->post("/planning/{$cours->id}/{$date}/reservations", ['client_id' => $b->id]);
        $this->assertSame('attente', Reservation::where('client_id', $b->id)->value('statut'));

        $this->post('/reservations/'.Reservation::where('client_id', $a->id)->value('id').'/statut', ['statut' => 'annulee']);
        $this->assertSame('reservee', Reservation::where('client_id', $b->id)->value('statut'));
    }

    public function test_espace_membre_par_lien_personnel(): void
    {
        $client = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Konan', 'prenoms' => 'Esther', 'telephone' => '0711523890']);
        $this->abonner($client);
        $cours = Cours::create(['nom' => 'Zumba', 'jour_semaine' => today()->addDay()->isoWeekday(), 'heure' => '18:30', 'capacite' => 10, 'actif' => true]);

        $this->post("/clients/{$client->id}/lien-membre")->assertSessionHas('lien_membre');
        $url = session('lien_membre')['url'];
        $this->assertStringStartsWith('https://wa.me/', session('lien_membre')['whatsapp']);
        $this->post('/logout');

        $this->get('/membre')->assertRedirect('/membre/connexion');
        $this->get('/membre/acces/mauvais-jeton')->assertRedirect('/membre/connexion');
        $this->get($url)->assertRedirect('/membre');

        $this->get('/membre')->assertOk()->assertSee('Bonjour Esther')->assertSee($client->code_acces);
        $this->get('/membre/qr.svg')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        foreach (['/membre/historique', '/membre/progression', '/membre/cours', '/membre/renouveler'] as $page) {
            $this->get($page)->assertOk();
        }
        $this->get('/membre/renouveler')->assertSee('Paiement à l\'accueil', false);

        $this->post("/membre/cours/{$cours->id}/reserver", ['date' => today()->addDay()->toDateString()])->assertSessionHas('succes');
        $this->assertSame(1, Reservation::where('client_id', $client->id)->count());

        // Un nouveau lien invalide l'ancien
        $client->genererJetonMembre();
        $this->post('/membre/deconnexion');
        $this->get($url)->assertRedirect('/membre/connexion');
    }

    public function test_renouvellement_en_ligne_cinetpay(): void
    {
        config(['salle.paiement_en_ligne.cinetpay_apikey' => 'cle', 'salle.paiement_en_ligne.cinetpay_site_id' => '123']);
        Http::fake([
            '*/payment/check' => Http::response(['code' => '00', 'data' => ['status' => 'ACCEPTED', 'amount' => '15000']]),
            '*/payment' => Http::response(['code' => '201', 'data' => ['payment_url' => 'https://checkout.cinetpay.com/abc']]),
        ]);
        $client = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Yao', 'telephone' => '0143880562']);
        $jeton = $client->genererJetonMembre();

        $this->get("/membre/acces/{$jeton}");
        $this->post('/membre/renouveler', ['formule_id' => $this->formule('Mensuel')->id])->assertRedirect('https://checkout.cinetpay.com/abc');

        $transaction = PaiementEnLigne::firstOrFail();
        $this->post('/paiement/notification', ['cpm_trans_id' => $transaction->transaction_id])->assertOk();
        $this->post('/paiement/notification', ['cpm_trans_id' => $transaction->transaction_id]);

        $this->assertSame('accepte', $transaction->fresh()->statut);
        $this->assertSame(1, Paiement::where('mode', 'en_ligne')->count());
        $this->assertNotNull($client->fresh()->finDesDroits());
        $this->get("/membre/paiement/{$transaction->transaction_id}")->assertOk()->assertSee('Merci');
    }

    public function test_prospect_converti_en_client(): void
    {
        $this->actingAs($this->caissiere)->post('/prospects', ['nom' => 'Yapi Rodrigue', 'telephone' => '0102030405', 'statut' => 'nouveau', 'source' => 'Facebook'])->assertRedirect();
        $prospect = Prospect::firstOrFail();

        $this->post("/prospects/{$prospect->id}/convertir")->assertRedirect();
        $this->assertSame('inscrit', $prospect->fresh()->statut);
        $this->assertDatabaseHas('clients', ['nom' => 'Yapi Rodrigue', 'telephone' => '0102030405']);
    }

    public function test_exports_rapports_campagnes_et_journal(): void
    {
        $client = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Soro', 'telephone' => '0701020304']);
        $this->abonner($client);

        $this->actingAs($this->admin);
        $csv = $this->get('/admin/exports/paiements')->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Soro', $csv);
        $this->assertStringContainsString('15000', $csv);
        $this->get('/admin/exports/clients')->assertOk();
        $this->get('/admin/exports/passages')->assertOk();

        $this->get('/admin/rapports')->assertOk()->assertViewHas('mois', fn ($m) => $m->last()['total'] === 15000);

        $this->post('/admin/campagnes', ['nom' => 'Promo', 'segment' => 'actifs', 'contenu' => 'Bonjour {prenom}, promo à {salle} !'])->assertRedirect();
        $this->assertSame(1, Message::where('type', 'campagne')->count());
        $this->assertStringContainsString('Bonjour Soro', Message::first()->contenu);

        $this->post('/admin/caissiers', ['name' => 'Mariam', 'email' => 'mariam@gymflow.local', 'caisse_id' => Caisse::first()->id, 'actif' => '1',
            'password' => 'MotDePasse!1', 'password_confirmation' => 'MotDePasse!1']);
        $this->assertTrue(Journal::where('action', 'utilisateur.cree')->exists());
        $this->get('/admin/journal?action=utilisateur.cree')->assertOk()->assertSee('Mariam');
    }

    public function test_recherche_universelle_et_mon_compte(): void
    {
        Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Koné', 'prenoms' => 'Aminata', 'telephone' => '07 48 12 90 33', 'empreinte_id' => '42']);

        $this->actingAs($this->caissiere)->getJson('/recherche?q=0748')->assertOk()->assertJsonPath('clients.0.nom', 'Koné Aminata')
            ->assertJsonPath('clients.0.actions.0.libelle', 'Renouveler');
        $this->getJson('/recherche?q=42')->assertJsonPath('clients.0.statut', 'Expiré');

        $this->put('/compte', ['name' => 'Awa K.', 'email' => 'caisse@gymflow.local', 'mot_de_passe_actuel' => 'mauvais'])->assertSessionHasErrors('mot_de_passe_actuel');
        $this->put('/compte', ['name' => 'Awa K.', 'email' => 'caisse@gymflow.local', 'mot_de_passe_actuel' => 'ChangeMoi!2026',
            'password' => 'NouveauMdp!9', 'password_confirmation' => 'NouveauMdp!9'])->assertSessionHasNoErrors();
        $this->assertTrue(password_verify('NouveauMdp!9', $this->caissiere->fresh()->password));
        $this->assertSame('Awa K.', $this->caissiere->fresh()->name);
    }

    public function test_mot_de_passe_oublie(): void
    {
        Notification::fake();

        $this->post('/mot-de-passe-oublie', ['email' => 'admin@gymflow.local'])->assertSessionHas('succes');
        $this->post('/mot-de-passe-oublie', ['email' => 'inconnu@gymflow.local'])->assertSessionHas('succes');

        Notification::assertSentTo($this->admin, ResetPassword::class, function ($notification) {
            $this->post('/reinitialiser', [
                'token' => $notification->token, 'email' => 'admin@gymflow.local',
                'password' => 'Nouveau!2026', 'password_confirmation' => 'Nouveau!2026',
            ])->assertRedirect('/login');

            return true;
        });

        $this->assertTrue(password_verify('Nouveau!2026', $this->admin->fresh()->password));
    }
}
