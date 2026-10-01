<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Service;
use App\Models\User;
use App\Models\Vente;
use App\Services\KpiService;
use App\Support\Periode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SalonFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $gerante;

    private User $assistante;

    private Service $brushing;

    private Service $tresses;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-01 10:00:00'); // jeudi

        $this->gerante = User::factory()->gerante()->create();
        $this->assistante = User::factory()->create(['name' => 'Awa']);

        $categorie = Categorie::create(['nom' => 'Coiffure', 'couleur' => '#2563eb']);
        $this->brushing = Service::create(['categorie_id' => $categorie->id, 'code' => 'COI-001', 'nom' => 'Brushing', 'prix' => 3000]);
        $this->tresses = Service::create(['categorie_id' => $categorie->id, 'code' => 'TRE-001', 'nom' => 'Tresses', 'prix' => 10000]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function vendre(User $user, array $lignes, string $mode = 'especes', ?int $recu = null)
    {
        return $this->actingAs($user)->postJson('/caisse', [
            'lignes' => $lignes,
            'mode_paiement' => $mode,
            'montant_recu' => $recu,
        ]);
    }

    public function test_la_vente_est_calculee_avec_les_prix_de_la_base(): void
    {
        $this->vendre($this->assistante, [
            ['service_id' => $this->brushing->id, 'quantite' => 2, 'prix' => 1],
            ['service_id' => $this->tresses->id, 'quantite' => 1],
        ], 'especes', 20000)
            ->assertCreated()
            ->assertJsonPath('numero', 'T-20261001-0001');

        $vente = Vente::with('lignes')->first();
        $this->assertSame(16000, $vente->total);
        $this->assertSame(20000, $vente->montant_recu);
        $this->assertSame(4000, $vente->monnaie_rendue);
        $this->assertSame($this->assistante->id, $vente->user_id);
        $this->assertCount(2, $vente->lignes);
    }

    public function test_un_meme_service_clique_plusieurs_fois_est_regroupe(): void
    {
        $this->vendre($this->assistante, [
            ['service_id' => $this->brushing->id, 'quantite' => 1],
            ['service_id' => $this->brushing->id, 'quantite' => 1],
        ], 'mobile_money')->assertCreated();

        $vente = Vente::with('lignes')->first();
        $this->assertCount(1, $vente->lignes);
        $this->assertSame(2, $vente->lignes->first()->quantite);
        $this->assertSame(6000, $vente->total);
        $this->assertNull($vente->montant_recu);
    }

    public function test_les_numeros_de_ticket_se_suivent_et_repartent_chaque_jour(): void
    {
        $ligne = [['service_id' => $this->brushing->id, 'quantite' => 1]];

        $this->vendre($this->assistante, $ligne)->assertJsonPath('numero', 'T-20261001-0001');
        $this->vendre($this->gerante, $ligne)->assertJsonPath('numero', 'T-20261001-0002');

        Carbon::setTestNow('2026-10-02 09:00:00');
        $this->vendre($this->assistante, $ligne)->assertJsonPath('numero', 'T-20261002-0001');
    }

    public function test_ticket_vide_service_desactive_ou_especes_insuffisantes_refuses(): void
    {
        $this->vendre($this->assistante, [])->assertUnprocessable();

        $this->vendre($this->assistante, [['service_id' => $this->tresses->id, 'quantite' => 1]], 'especes', 5000)
            ->assertUnprocessable()->assertJsonValidationErrors('montant_recu');

        $this->brushing->update(['actif' => false]);
        $this->vendre($this->assistante, [['service_id' => $this->brushing->id, 'quantite' => 1]])
            ->assertUnprocessable()->assertJsonValidationErrors('lignes');

        $this->assertSame(0, Vente::count());
    }

    public function test_changer_un_prix_ne_modifie_pas_les_anciens_tickets(): void
    {
        $this->vendre($this->assistante, [['service_id' => $this->brushing->id, 'quantite' => 1]]);
        $this->brushing->update(['prix' => 3500, 'nom' => 'Brushing long']);

        $vente = Vente::with('lignes')->first();
        $this->assertSame(3000, $vente->total);
        $this->assertSame('Brushing', $vente->lignes->first()->libelle);

        $this->actingAs($this->gerante)->get('/tickets/'.$vente->id)
            ->assertOk()->assertSee('Brushing')->assertSee('3 000 FCFA');
    }

    public function test_assistante_limitee_a_la_caisse_et_a_ses_kpi(): void
    {
        $this->actingAs($this->assistante);

        $this->get('/caisse')->assertOk()->assertSee('Brushing');
        $this->get('/mes-kpi')->assertOk();
        $this->get('/')->assertRedirect('/caisse');

        foreach (['/dashboard', '/ventes', '/services', '/services/create', '/comptes'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/comptes', ['name' => 'X', 'email' => 'x@x.ci', 'password' => 'secret1', 'password_confirmation' => 'secret1'])->assertForbidden();
        $this->put('/services/'.$this->brushing->id, ['prix' => 1])->assertForbidden();
        $this->assertSame(3000, $this->brushing->fresh()->prix);
    }

    public function test_assistante_ne_voit_pas_les_tickets_des_autres(): void
    {
        $collegue = User::factory()->create();
        $this->vendre($collegue, [['service_id' => $this->brushing->id, 'quantite' => 1]]);
        $vente = Vente::first();

        $this->actingAs($this->assistante)->get('/tickets/'.$vente->id)->assertForbidden();
        $this->actingAs($collegue)->get('/tickets/'.$vente->id)->assertOk();
        $this->actingAs($this->gerante)->get('/tickets/'.$vente->id)->assertOk();
    }

    public function test_mes_kpi_ne_montre_que_les_ventes_de_l_assistante(): void
    {
        $collegue = User::factory()->create();
        $this->vendre($this->assistante, [['service_id' => $this->brushing->id, 'quantite' => 1]]);
        $this->vendre($collegue, [['service_id' => $this->tresses->id, 'quantite' => 3]]);

        $kpi = app(KpiService::class)->calculer(Periode::depuis('jour'), $this->assistante);
        $this->assertSame(3000, $kpi['resume']['ca']);
        $this->assertSame(1, $kpi['resume']['tickets']);

        $this->actingAs($this->assistante)->get('/mes-kpi')
            ->assertOk()->assertSee('3 000')->assertDontSee('30 000');
    }

    public function test_gerante_limitee_a_trois_assistantes_actives(): void
    {
        $this->actingAs($this->gerante);
        // Une assistante existe déjà (setUp) : on peut en créer deux de plus
        foreach (['b', 'c'] as $lettre) {
            $this->post('/comptes', [
                'name' => "Assistante {$lettre}", 'email' => "{$lettre}@salon.ci",
                'password' => 'secret1', 'password_confirmation' => 'secret1',
            ])->assertSessionHasNoErrors();
        }

        $this->post('/comptes', [
            'name' => 'Quatrième', 'email' => 'd@salon.ci', 'password' => 'secret1', 'password_confirmation' => 'secret1',
        ])->assertSessionHasErrors('name');
        $this->assertSame(3, User::assistantes()->count());

        // Désactiver libère une place, réactiver au-delà de la limite est refusé
        $this->patch("/comptes/{$this->assistante->id}/activation")->assertSessionHasNoErrors();
        $this->assertFalse($this->assistante->fresh()->actif);
        $this->post('/comptes', [
            'name' => 'Quatrième', 'email' => 'd@salon.ci', 'password' => 'secret1', 'password_confirmation' => 'secret1',
        ])->assertSessionHasNoErrors();
        $this->patch("/comptes/{$this->assistante->id}/activation")->assertSessionHasErrors('name');
        $this->assertFalse($this->assistante->fresh()->actif);
    }

    public function test_compte_desactive_ne_peut_plus_se_connecter_ni_vendre(): void
    {
        $this->assistante->update(['actif' => false]);

        $this->post('/login', ['email' => $this->assistante->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($this->assistante)->get('/caisse')->assertRedirect('/login');
    }

    public function test_connexion_redirige_selon_le_role(): void
    {
        $this->post('/login', ['email' => $this->gerante->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->post('/logout');
        $this->post('/login', ['email' => $this->assistante->email, 'password' => 'password'])->assertRedirect('/caisse');
    }

    public function test_kpi_jour_semaine_et_evolution(): void
    {
        // Hier (mercredi) : 1 brushing
        Carbon::setTestNow('2026-09-30 15:00:00');
        $this->vendre($this->assistante, [['service_id' => $this->brushing->id, 'quantite' => 1]]);

        // Aujourd'hui (jeudi) : 2 tickets
        Carbon::setTestNow('2026-10-01 10:00:00');
        $this->vendre($this->assistante, [['service_id' => $this->tresses->id, 'quantite' => 1]]);
        $this->vendre($this->gerante, [['service_id' => $this->brushing->id, 'quantite' => 1]], 'carte');

        // Semaine dernière : 1 ticket
        Carbon::setTestNow('2026-09-22 11:00:00');
        $this->vendre($this->assistante, [['service_id' => $this->brushing->id, 'quantite' => 2]]);
        // Vendredi dernier : hors comparaison, la semaine en cours n'est qu'à jeudi
        Carbon::setTestNow('2026-09-25 11:00:00');
        $this->vendre($this->assistante, [['service_id' => $this->tresses->id, 'quantite' => 1]]);
        Carbon::setTestNow('2026-10-01 18:00:00');

        $kpi = app(KpiService::class);

        $jour = $kpi->calculer(Periode::depuis('jour'));
        $this->assertSame(13000, $jour['resume']['ca']);
        $this->assertSame(2, $jour['resume']['tickets']);
        $this->assertSame(6500, $jour['resume']['panier_moyen']);
        $this->assertSame(333.3, $jour['evolution']['ca']); // 13 000 vs 3 000 hier
        $this->assertSame('Tresses', $jour['top_services'][0]['libelle']);
        $this->assertCount(2, $jour['par_personne']);

        $semaine = $kpi->calculer(Periode::depuis('semaine'));
        $this->assertSame(16000, $semaine['resume']['ca']);
        $this->assertSame(3, $semaine['resume']['tickets']);
        $this->assertSame(6000, $semaine['precedent']['ca']); // lundi → jeudi 18h de la semaine dernière
        $this->assertSame(166.7, $semaine['evolution']['ca']);
        $this->assertCount(7, $semaine['courbe']);

        $dates = $kpi->calculer(Periode::depuis('dates', '2026-09-22', '2026-10-01'));
        $this->assertSame(32000, $dates['resume']['ca']);
    }

    public function test_ticket_annule_exclu_des_kpi(): void
    {
        $this->vendre($this->assistante, [['service_id' => $this->tresses->id, 'quantite' => 1]]);
        $this->vendre($this->assistante, [['service_id' => $this->brushing->id, 'quantite' => 1]]);
        $vente = Vente::where('total', 10000)->first();

        $this->actingAs($this->assistante)->post("/ventes/{$vente->id}/annuler", ['motif' => 'Erreur'])->assertForbidden();
        $this->actingAs($this->gerante)->post("/ventes/{$vente->id}/annuler", [])->assertSessionHasErrors('motif');
        $this->actingAs($this->gerante)->post("/ventes/{$vente->id}/annuler", ['motif' => 'Erreur de saisie'])->assertSessionHasNoErrors();

        $kpi = app(KpiService::class)->calculer(Periode::depuis('jour'));
        $this->assertSame(3000, $kpi['resume']['ca']);
        $this->assertSame(1, $kpi['annulations']['nombre']);
        $this->assertSame(10000, $kpi['annulations']['montant']);
    }

    public function test_les_pages_de_la_gerante_s_affichent(): void
    {
        $this->vendre($this->assistante, [['service_id' => $this->tresses->id, 'quantite' => 1]]);
        $this->actingAs($this->gerante);

        foreach (['jour', 'semaine', 'mois'] as $periode) {
            $this->get('/dashboard?periode='.$periode)->assertOk()->assertSee('10 000');
        }
        $this->get('/dashboard?periode=dates&du=2026-09-01&au=2026-10-01')->assertOk();
        $this->get('/dashboard?periode=dates&du=nimporte&au=quoi')->assertOk();
        $this->get('/ventes')->assertOk()->assertSee('T-20261001-0001');
        $this->get('/services')->assertOk()->assertSee('Brushing');
        $this->get('/services/create')->assertOk();
        $this->get('/services/'.$this->brushing->id.'/edit')->assertOk();
        $this->get('/comptes')->assertOk()->assertSee('Awa');
        $this->get('/caisse')->assertOk();
        $this->get('/mot-de-passe')->assertOk();
    }

    public function test_gerante_gere_les_services(): void
    {
        $this->actingAs($this->gerante);
        $categorie = Categorie::first();

        $this->post('/services', ['categorie_id' => $categorie->id, 'code' => 'coi-009', 'nom' => 'Chignon', 'prix' => 6000, 'actif' => '1'])
            ->assertRedirect('/services');
        $chignon = Service::where('code', 'COI-009')->firstOrFail();
        $this->assertTrue($chignon->actif);

        $this->put('/services/'.$chignon->id, ['categorie_id' => $categorie->id, 'code' => 'COI-009', 'nom' => 'Chignon', 'prix' => 6500])
            ->assertRedirect('/services');
        $this->assertSame(6500, $chignon->fresh()->prix);
        $this->assertFalse($chignon->fresh()->actif); // case décochée = masqué en caisse

        $this->get('/caisse')->assertDontSee('COI-009');

        $this->delete('/categories/'.$categorie->id)->assertSessionHas('erreur');
        $this->delete('/services/'.$chignon->id);
        $this->assertNull($chignon->fresh());
    }
}
