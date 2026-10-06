<?php

namespace Tests\Feature;

use App\Models\Abonnement;
use App\Models\Client;
use App\Models\CommandePointeuse;
use App\Models\Formule;
use App\Models\Paiement;
use App\Models\Parametre;
use App\Models\Passage;
use App\Models\User;
use App\Services\KpiService;
use App\Services\PointageService;
use App\Services\PointeuseService;
use App\Services\SynchroPointeuseService;
use App\Support\Horaires;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Grille EPIKAÏZO : carnet Fidélité, passe « 1 séance par jour », avantages des formules. */
class GrilleTarifaireTest extends TestCase
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
        $this->travelTo(CarbonImmutable::parse('2026-10-05 07:00:00'));
    }

    private function badge(string $numero, string $quand): Passage
    {
        return app(PointageService::class)->parEmpreinte($numero, null, null, CarbonImmutable::parse($quand));
    }

    private function abonne(string $formule, string $numero): Client
    {
        $client = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Membre '.$numero, 'empreinte_id' => $numero]);
        Abonnement::create([
            'client_id' => $client->id, 'formule_id' => Formule::where('nom', $formule)->value('id'), 'date_debut' => today(),
            'date_fin' => today()->addDays(29), 'montant' => 25000, 'statut' => Abonnement::STATUT_ACTIF,
        ]);

        return $client;
    }

    public function test_carnet_fidelite_vendu_puis_decompte_a_chaque_arrivee(): void
    {
        $client = Client::create(['type' => Client::TYPE_JOURNALIER, 'nom' => 'Fidèle', 'empreinte_id' => '30']);
        $sansNumero = Client::create(['type' => Client::TYPE_JOURNALIER, 'nom' => 'Sans numéro']);
        $this->actingAs($this->caissiere);

        // Minimum 5 séances, et un n° d'empreinte pour pouvoir badger
        $this->post('/caisse/carnet', ['client_id' => $client->id, 'seances' => 4, 'mode' => 'especes'])->assertSessionHasErrors('seances');
        $this->post('/caisse/carnet', ['client_id' => $sansNumero->id, 'seances' => 5, 'mode' => 'especes'])->assertSessionHasErrors('client_id');
        $this->get('/caisse')->assertSee('Carnet Fidélité')->assertSee('2 000');

        $this->post('/caisse/carnet', ['client_id' => $client->id, 'seances' => 5, 'mode' => 'especes'])->assertSessionHasNoErrors();
        $carnet = Paiement::where('type', Paiement::TYPE_CARNET)->firstOrFail();
        $this->assertSame([10000, 5], [$carnet->montant, $carnet->quantite]);
        $this->get("/recus/{$carnet->numero_recu}")->assertOk()->assertSee('Carnet Fidélité · 5 séances')->assertSee('2 000');
        $this->assertSame(5, $client->seancesCarnet());

        // Jour 1 : l'arrivée décompte une séance, le départ ne compte pas
        $arrivee = $this->badge('30', '2026-10-05 08:00:00');
        $this->assertSame([Passage::STATUT_AUTORISE, Passage::SENS_ENTREE, $carnet->id], [$arrivee->statut, $arrivee->sens, $arrivee->paiement_id]);
        $this->assertSame(Passage::SENS_DEPART, $this->badge('30', '2026-10-05 10:00:00')->sens);
        $this->assertSame(4, $client->seancesCarnet());

        // Jours 2 à 5 : une séance par jour jusqu'à épuisement
        foreach (['06', '07', '08', '09'] as $jour) {
            $this->assertTrue($this->badge('30', "2026-10-{$jour} 08:00:00")->estAutorise());
        }
        $this->assertSame(0, $client->seancesCarnet());

        // Dernière séance utilisée aujourd'hui : la pointeuse le laisse encore sortir ce soir, plus demain
        $this->travelTo(CarbonImmutable::parse('2026-10-09 09:00:00'));
        $this->assertSame(today()->endOfDay()->toDateTimeString(), app(PointeuseService::class)->validite($client)[1]->toDateTimeString());

        $refus = $this->badge('30', '2026-10-10 08:00:00');
        $this->assertSame([Passage::STATUT_REFUSE, 'carnet_epuise'], [$refus->statut, $refus->motif]);

        // Recettes : le carnet compte avec les séances vendues
        $this->assertSame(5, app(KpiService::class)->chiffresCaisse(CarbonImmutable::parse('2026-10-05'))['passages_vendus']);
    }

    public function test_carnet_ouvre_la_pointeuse_tant_qu_il_reste_des_seances(): void
    {
        $client = Client::create(['type' => Client::TYPE_JOURNALIER, 'nom' => 'Fidèle', 'empreinte_id' => '32']);
        $this->assertSame(today()->subDay()->endOfDay()->toDateTimeString(), app(PointeuseService::class)->validite($client)[1]->toDateTimeString());

        $this->actingAs($this->caissiere)->post('/caisse/carnet', ['client_id' => $client->id, 'seances' => 5, 'mode' => 'especes']);
        $this->assertTrue(app(PointeuseService::class)->validite($client)[1]->gt(today()->addMonths(6)));
    }

    public function test_passe_simple_une_seance_par_jour(): void
    {
        $simple = $this->abonne('Passe mensuelle Simple', '31');
        $illimite = $this->abonne('Passe mensuelle Illimitée', '33');
        $ouvert = ['etat' => 'ouvert', 'debut' => '06:00', 'fin' => '21:00'];
        Horaires::enregistrer([1 => $ouvert, 2 => $ouvert]); // 05/10/2026 = lundi, 06/10 = mardi

        $this->assertSame(Passage::SENS_ENTREE, $this->badge('31', '2026-10-05 08:00:00')->sens);
        // Passe Simple : le 2e badge est le départ, même avant l'heure de fin des séances
        $this->assertSame(Passage::SENS_DEPART, $this->badge('31', '2026-10-05 10:30:00')->sens);
        $retour = $this->badge('31', '2026-10-05 17:00:00');
        $this->assertSame([Passage::STATUT_REFUSE, 'seance_du_jour_faite'], [$retour->statut, $retour->motif]);
        $this->assertSame('Séance du jour déjà faite (passe 1 séance par jour)', $retour->message());

        // La pointeuse le refuse jusqu'à demain, puis il retrouve ses droits
        $this->travelTo(CarbonImmutable::parse('2026-10-05 18:00:00'));
        $this->assertSame('2026-10-04 23:59:59', app(PointeuseService::class)->validite($simple)[1]->toDateTimeString());
        $this->travelTo(CarbonImmutable::parse('2026-10-06 06:30:00'));
        $this->assertSame('2026-11-03 23:59:59', app(PointeuseService::class)->validite($simple)[1]->toDateTimeString());
        $this->assertSame(Passage::SENS_ENTREE, $this->badge('31', '2026-10-06 07:00:00')->sens);

        // Illimitée : rien ne change (le 2e badge avant 21:00 reste « déjà enregistré »)
        $this->badge('33', '2026-10-06 08:00:00');
        $this->assertSame(Passage::SENS_DEJA, $this->badge('33', '2026-10-06 10:30:00')->sens);
        $this->assertTrue($this->badge('33', '2026-10-06 17:00:00')->estAutorise());
        $this->assertNotNull($illimite);
    }

    public function test_acces_rouvert_chaque_matin_pour_les_passes_simples(): void
    {
        $this->actingAs($this->admin)->post('/admin/pointeuses', [
            'nom' => 'Entrée', 'adresse_ip' => '192.168.50.64', 'port' => 80, 'identifiant' => 'admin', 'mot_de_passe' => 'Secret.Pointeuse1',
        ])->assertSessionHasNoErrors();
        $this->abonne('Passe mensuelle Simple', '31');
        $this->abonne('Passe mensuelle Illimitée', '33');
        CommandePointeuse::query()->delete();

        $synchro = app(SynchroPointeuseService::class);
        $this->assertSame(1, $synchro->nouvelleJournee()); // seul le membre « 1 séance par jour »
        $this->assertSame(0, $synchro->nouvelleJournee()); // une seule fois par jour
        $this->assertSame(1, CommandePointeuse::where('statut', CommandePointeuse::EN_ATTENTE)->count());

        $this->travelTo(CarbonImmutable::parse('2026-10-06 00:00:05'));
        $this->assertSame(1, $synchro->nouvelleJournee());
    }

    public function test_acces_et_avantages_des_formules(): void
    {
        $formule = Formule::where('nom', 'Passe mensuelle Illimitée')->firstOrFail();
        $this->actingAs($this->admin)->put("/admin/formules/{$formule->id}", [
            'nom' => $formule->nom, 'duree_jours' => 30, 'prix' => 40000, 'actif' => 1,
            'seances_par_jour' => '', 'description' => "  2 serviettes offertes \n\n Coaching individuel  ",
        ])->assertSessionHasNoErrors();
        $formule->refresh();
        $this->assertNull($formule->seances_par_jour);
        $this->assertSame(['2 serviettes offertes', 'Coaching individuel'], $formule->avantages());
        $this->get('/admin/formules')->assertOk()->assertSee('1 séance par jour');

        $this->put('/admin/tarif-journalier', ['tarif_journalier' => 2500, 'tarif_fidelite' => 1800])->assertSessionHasNoErrors();
        $this->assertSame([2500, 1800], [Parametre::tarifJournalier(), Parametre::tarifFidelite()]);

        // La caissière voit les avantages en choisissant la formule, puis sur le ticket
        $client = Client::create(['type' => Client::TYPE_JOURNALIER, 'nom' => 'Nouveau']);
        $this->actingAs($this->caissiere)->get('/caisse')->assertSee('Coaching individuel')->assertSee("1\u{00A0}séance/jour");
        $this->post('/caisse/abonnement', ['client_id' => $client->id, 'formule_id' => $formule->id, 'mode' => 'especes']);
        $this->get('/recus/'.Paiement::latest('id')->value('numero_recu'))
            ->assertSee('Vos avantages')->assertSee('2 serviettes offertes')->assertSee('Illimité');
    }
}
