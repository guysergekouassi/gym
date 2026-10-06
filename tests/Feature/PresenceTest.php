<?php

namespace Tests\Feature;

use App\Models\Abonnement;
use App\Models\Client;
use App\Models\Formule;
use App\Models\Passage;
use App\Models\User;
use App\Services\PointageService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Entrées / départs : 1er badge = arrivée, 2e = départ, ensuite « déjà enregistré ». */
class PresenceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Client $membre;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        User::query()->update(['doit_changer_mdp' => false]);
        $this->admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();
        $this->travelTo(CarbonImmutable::parse('2026-10-04 07:00:00'));

        $this->membre = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Meledje', 'prenoms' => 'Agnimel', 'empreinte_id' => '1']);
        Abonnement::create([
            'client_id' => $this->membre->id, 'formule_id' => Formule::where('nom', 'Passe mensuelle Illimitée')->value('id'), 'date_debut' => today(),
            'date_fin' => today()->addDays(29), 'montant' => 15000, 'statut' => Abonnement::STATUT_ACTIF,
        ]);
    }

    private function badge(string $heure): Passage
    {
        return app(PointageService::class)->parEmpreinte('1', null, null, CarbonImmutable::parse("2026-10-04 {$heure}"));
    }

    public function test_arrivee_depart_puis_deja_enregistre(): void
    {
        $arrivee = $this->badge('08:00:05');
        $this->assertSame([Passage::SENS_ENTREE, 'Bienvenue'], [$arrivee->sens, $arrivee->message()]);

        // Doigt reposé tout de suite : pas de faux départ
        $this->assertSame($arrivee->id, $this->badge('08:01:00')->id);

        $depart = $this->badge('10:30:20');
        $this->assertSame([Passage::SENS_DEPART, 'À bientôt'], [$depart->sens, $depart->message()]);

        $encore = $this->badge('11:00:00');
        $this->assertSame([Passage::SENS_DEJA, 'Déjà enregistré'], [$encore->sens, $encore->message()]);

        // Le lendemain, on repart sur une arrivée
        $this->assertSame(Passage::SENS_ENTREE, app(PointageService::class)
            ->parEmpreinte('1', null, null, CarbonImmutable::parse('2026-10-05 09:00:00'))->sens);
    }

    public function test_page_entrees_departs(): void
    {
        $this->badge('08:00:05');
        $this->badge('10:30:20');
        $this->travelTo(CarbonImmutable::parse('2026-10-04 12:00:00'));

        $this->actingAs($this->admin)->get('/entrees-departs')
            ->assertOk()
            ->assertSee('Meledje Agnimel')
            ->assertSee('08:00:05')
            ->assertSee('10:30:20')
            ->assertSee('2 h 30 min 15 s');

        // Le tableau de bord compte une venue, pas deux (le départ n'est pas une entrée)
        $periode = \App\Support\Periode::depuisRequete(\Illuminate\Http\Request::create('/dashboard'));
        $this->assertSame(1, app(\App\Services\KpiService::class)->chiffresPeriode($periode)['entrees']);

        $this->get('/entrees-departs?q=inconnu')->assertOk()->assertDontSee('08:00:05');

        // Accessible à la caissière aussi
        $caissier = User::where('role', User::ROLE_CAISSIER)->firstOrFail();
        $this->actingAs($caissier)->get('/entrees-departs')->assertOk();
    }

    public function test_numeros_du_personnel_reserves(): void
    {
        $this->actingAs($this->admin)->post('/clients', [
            'nom' => 'Employé', 'type' => Client::TYPE_ABONNE, 'empreinte_id' => '900000001',
        ])->assertSessionHasErrors('empreinte_id');

        $this->assertSame(2, \App\Support\Empreinte::prochainNumero());
    }

    public function test_depart_non_badge(): void
    {
        $this->badge('08:00:05');
        \App\Support\Horaires::enregistrer([7 => ['etat' => 'ouvert', 'debut' => '06:00', 'fin' => '21:00']]); // 04/10/2026 = dimanche

        $this->travelTo(CarbonImmutable::parse('2026-10-04 12:00:00'));
        $this->actingAs($this->admin)->get('/entrees-departs')->assertSee('Encore présent')->assertDontSee('Non badgé');

        // Après la fermeture, ou les jours suivants : « Non badgé »
        $this->travelTo(CarbonImmutable::parse('2026-10-04 21:30:00'));
        $this->get('/entrees-departs')->assertSee('Non badgé')->assertDontSee('Encore présent');

        $this->travelTo(CarbonImmutable::parse('2026-10-05 10:00:00'));
        $this->get('/entrees-departs?annee=2026&mois=10&jour=4')->assertSee('08:00:05')->assertSee('Non badgé');
    }

    public function test_depart_seulement_a_partir_de_l_heure_de_fin(): void
    {
        \App\Support\Horaires::enregistrer([7 => ['etat' => 'ouvert', 'debut' => '06:00', 'fin' => '21:00']]); // 04/10/2026 = dimanche

        $this->assertSame(Passage::SENS_ENTREE, $this->badge('08:00:05')->sens);
        $avant = $this->badge('10:30:20');
        $this->assertSame(Passage::SENS_DEJA, $avant->sens); // avant 21:00 : pas un départ

        $this->travelTo(CarbonImmutable::parse('2026-10-04 10:30:25'));
        $this->actingAs($this->admin)->getJson('/accueil/dernier')
            ->assertJson(['sens' => 'deja', 'detail' => 'Arrivée déjà enregistrée. Le départ se badge à partir de 21:00.']);

        $this->assertSame(Passage::SENS_DEPART, $this->badge('21:00:00')->sens);
        $this->assertSame(Passage::SENS_DEJA, $this->badge('21:30:00')->sens);
    }
}
