<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Paiement;
use App\Models\User;
use App\Support\Periode;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PeriodeTest extends TestCase
{
    use RefreshDatabase;

    private function periode(array $params): Periode
    {
        return Periode::depuisRequete(Request::create('/dashboard', 'GET', $params));
    }

    public function test_par_defaut_aujourdhui(): void
    {
        $p = $this->periode([]);
        $this->assertSame('jour', $p->granularite);
        $this->assertTrue($p->du->isToday());
        $this->assertSame('du jour', $p->suffixe());
    }

    public function test_calendrier_annee_mois_semaine_jour(): void
    {
        $annee = $this->periode(['annee' => '2025', 'mois' => '']);
        $this->assertSame(['annee', '2025-01-01', '2025-12-31'], [$annee->granularite, $annee->du->toDateString(), $annee->au->toDateString()]);
        $this->assertSame("de l'année", $annee->suffixe());

        $mois = $this->periode(['annee' => '2026', 'mois' => '2']);
        $this->assertSame(['mois', '2026-02-01', '2026-02-28'], [$mois->granularite, $mois->du->toDateString(), $mois->au->toDateString()]);
        $this->assertSame('2026-01-01', $mois->precedente()->du->toDateString());

        // Octobre 2026 commence un jeudi : semaine 1 = 1 → 4, semaine 2 = 5 → 11
        $semaines = Periode::semainesDuMois(2026, 10);
        $this->assertSame(['2026-10-01', '2026-10-04'], [$semaines[1]['du']->toDateString(), $semaines[1]['au']->toDateString()]);
        $this->assertSame(['2026-10-05', '2026-10-11'], [$semaines[2]['du']->toDateString(), $semaines[2]['au']->toDateString()]);
        $this->assertSame('2026-10-31', end($semaines)['au']->toDateString());

        $semaine = $this->periode(['annee' => '2026', 'mois' => '10', 'semaine' => '2']);
        $this->assertSame('semaine', $semaine->granularite);
        $this->assertSame([5, 6, 7, 8, 9, 10, 11], array_keys($semaine->joursProposes()));

        $jour = $this->periode(['annee' => '2026', 'mois' => '10', 'semaine' => '2', 'jour' => '7']);
        $this->assertSame(['jour', '2026-10-07'], [$jour->granularite, $jour->du->toDateString()]);
    }

    public function test_contraintes_appliquees_cote_serveur(): void
    {
        // Semaine ou jour sans mois : ignorés → l'année entière
        $this->assertSame('annee', $this->periode(['annee' => '2026', 'mois' => '', 'semaine' => '2', 'jour' => '5'])->granularite);
        // Jour hors de la semaine choisie : ignoré → la semaine
        $this->assertSame('semaine', $this->periode(['annee' => '2026', 'mois' => '10', 'semaine' => '1', 'jour' => '20'])->granularite);
        // 31 février, valeurs absurdes ou injectées : ignorées
        $this->assertSame('mois', $this->periode(['annee' => '2026', 'mois' => '2', 'jour' => '31'])->granularite);
        $this->assertSame(CarbonImmutable::today()->year, $this->periode(['annee' => "2026'; DROP TABLE clients;--", 'mois' => ''])->annee);
        $this->assertSame('annee', $this->periode(['annee' => '2026', 'mois' => '13'])->granularite);
    }

    public function test_mode_periode_du_au(): void
    {
        $p = $this->periode(['mode' => 'periode', 'du' => '2026-01-01', 'au' => '2026-10-03']);
        $this->assertSame(['periode', '2026-01-01', '2026-10-03'], [$p->granularite, $p->du->toDateString(), $p->au->toDateString()]);

        $inverse = $this->periode(['mode' => 'periode', 'du' => '2026-03-10', 'au' => '2026-03-01']);
        $this->assertSame('2026-03-01', $inverse->du->toDateString());

        $invalide = $this->periode(['mode' => 'periode', 'du' => 'hier', 'au' => '2026-99-99']);
        $this->assertTrue($invalide->au->isToday());
    }

    public function test_les_kpi_suivent_le_filtre(): void
    {
        $this->seed();
        User::query()->update(['doit_changer_mdp' => false]);
        $admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();

        $this->actingAs($admin)->post('/caisse/journalier', ['mode' => 'especes']);
        $ancien = Paiement::firstOrFail();
        $ancien->created_at = now()->subMonthNoOverflow()->startOfMonth()->addDays(2);
        $ancien->save();
        $this->post('/caisse/journalier', ['mode' => 'especes']);
        Client::query()->update(['created_at' => now()]);

        $tarif = Paiement::latest('id')->value('montant');

        $this->get('/dashboard')->assertOk()
            ->assertSee('Revenus du jour')
            ->assertViewHas('chiffres', fn ($c) => $c['recette'] === $tarif && $c['tickets'] === 1);

        $this->get('/dashboard?annee='.now()->year.'&mois='.now()->month)
            ->assertSee('Revenus du mois')->assertDontSee('Revenus du jour')
            ->assertViewHas('avant', fn ($c) => $c['recette'] === $tarif);

        $this->get('/dashboard?mode=periode&du='.now()->subMonths(2)->toDateString().'&au='.now()->toDateString())
            ->assertSee('Revenus de la période')
            ->assertViewHas('chiffres', fn ($c) => $c['recette'] === 2 * $tarif);

        $this->get('/admin/paiements?annee='.now()->year.'&mois=')->assertOk()->assertViewHas('nombre', 2);
    }

    public function test_comparaison_a_la_meme_heure(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-04 12:00:00'));
        $p = $this->periode([]);
        $this->assertSame('2026-10-03 12:00:00', $p->instantComparable()->format('Y-m-d H:i:s'));
        $this->assertSame('par rapport à la veille à la même heure', $p->reference());

        // Paiement d'hier à 18 h : il n'entre pas dans la comparaison de midi
        $client = Client::create(['nom' => 'Hier', 'type' => Client::TYPE_JOURNALIER]);
        $paiement = Paiement::create(['client_id' => $client->id, 'user_id' => User::factory()->create()->id, 'type' => Paiement::TYPE_JOURNALIER, 'montant' => 2000, 'mode' => 'especes', 'numero_recu' => 'R-T1']);
        $paiement->forceFill(['created_at' => '2026-10-03 18:00:00'])->save();

        $kpi = app(\App\Services\KpiService::class);
        $this->assertSame(0, $kpi->chiffresPeriode($p->precedente(), $p->instantComparable())['recette']);
        $this->assertSame(2000, $kpi->chiffresPeriode($p->precedente())['recette']);
    }
}
