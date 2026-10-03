<?php

namespace Tests\Feature;

use App\Models\Abonnement;
use App\Models\Client;
use App\Models\CommandePointeuse;
use App\Models\Formule;
use App\Models\Lecteur;
use App\Models\Passage;
use App\Models\User;
use App\Services\SynchroPointeuseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Liaison avec la pointeuse ZKTeco (protocole Cloud/ADMS « iclock »). */
class PointeuseTest extends TestCase
{
    use RefreshDatabase;

    private const SN = 'CQZ7224560123';

    private Lecteur $pointeuse;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        User::query()->update(['doit_changer_mdp' => false]);
        $this->admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();
        [$this->pointeuse] = Lecteur::creerAvecToken('Entrée');
        $this->pointeuse->update(['numero_serie' => self::SN]);
    }

    private function depuis(string $ip = '192.168.1.201'): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip]);
    }

    private function envoyerPointages(string $corps, string $ip = '192.168.1.201')
    {
        return $this->depuis($ip)->call('POST', '/iclock/cdata?SN='.self::SN.'&table=ATTLOG&Stamp=42', [], [], [], [], $corps);
    }

    public function test_la_pointeuse_recupere_sa_configuration_et_son_ip_est_memorisee(): void
    {
        $this->depuis()->get('/iclock/cdata?SN='.self::SN.'&options=all')
            ->assertOk()->assertSee('GET OPTION FROM: '.self::SN, false)->assertSee('Realtime=1', false);

        $this->pointeuse->refresh();
        $this->assertSame('192.168.1.201', $this->pointeuse->adresse_ip);
        $this->assertTrue($this->pointeuse->estEnLigne());
    }

    public function test_appareil_inconnu_ou_ip_differente_refuses(): void
    {
        $this->depuis()->get('/iclock/cdata?SN=INCONNU123')->assertForbidden();
        $this->depuis()->get('/iclock/cdata?SN=../../etc')->assertForbidden();

        $this->depuis()->get('/iclock/cdata?SN='.self::SN); // IP mémorisée
        $this->envoyerPointages("1\t".now()->format('Y-m-d H:i:s')."\t0\t1\t0", '10.0.0.66')->assertForbidden();
        $this->assertSame(0, Passage::count());

        $this->pointeuse->update(['actif' => false]);
        $this->depuis()->get('/iclock/cdata?SN='.self::SN)->assertForbidden();
    }

    public function test_pointage_temps_reel_autorise_ou_refuse(): void
    {
        $abonne = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Yao', 'empreinte_id' => '7']);
        Abonnement::create([
            'client_id' => $abonne->id, 'formule_id' => Formule::value('id'), 'date_debut' => today(),
            'date_fin' => today()->addDays(29), 'montant' => 15000, 'statut' => Abonnement::STATUT_ACTIF,
        ]);
        Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Expiré', 'empreinte_id' => '8']);

        $maintenant = now()->format('Y-m-d H:i:s');
        $this->envoyerPointages("7\t{$maintenant}\t0\t1\t0\t0\t0\n8\t{$maintenant}\t0\t1\t0\t0\t0\n99\t{$maintenant}\t0\t1\t0\nligne illisible\n")
            ->assertOk()->assertSee('OK: 3', false);

        $this->assertDatabaseHas('passages', ['client_id' => $abonne->id, 'statut' => 'autorise', 'methode' => 'empreinte', 'lecteur_id' => $this->pointeuse->id]);
        $this->assertDatabaseHas('passages', ['empreinte_id' => '8', 'statut' => 'refuse', 'motif' => 'abonnement_expire']);
        $this->assertDatabaseHas('passages', ['empreinte_id' => '99', 'statut' => 'refuse', 'motif' => 'empreinte_inconnue']);
        $this->assertSame('42', $this->pointeuse->fresh()->stamp_pointages);

        // La pointeuse renvoie le même lot (coupure réseau) : aucun doublon
        $this->envoyerPointages("7\t{$maintenant}\t0\t1\t0\n8\t{$maintenant}\t0\t1\t0");
        $this->assertSame(3, Passage::count());

        // L'écran d'accueil affiche le dernier passage
        $this->actingAs($this->admin)->getJson('/accueil/dernier')->assertOk()->assertJsonStructure(['autorise', 'client']);
    }

    public function test_dates_aberrantes_ignorees(): void
    {
        Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Yao', 'empreinte_id' => '7']);

        $this->envoyerPointages("7\t2001-01-01 10:00:00\t0\t1\n7\t2099-01-01 10:00:00\t0\t1\n7\tpas-une-date\t0\t1")
            ->assertSee('OK: 0', false);
        $this->assertSame(0, Passage::count());
    }

    public function test_commandes_envoyees_et_acquittees(): void
    {
        $this->actingAs($this->admin)->post('/clients', [
            'type' => Client::TYPE_ABONNE, 'nom' => "Kon\tan", 'prenoms' => "Aya\nDATA DELETE USERINFO PIN=1", 'empreinte_id' => '12',
        ])->assertSessionHasNoErrors();

        $commande = CommandePointeuse::firstOrFail();
        $this->assertStringStartsWith("DATA UPDATE USERINFO PIN=12\tName=", $commande->commande);
        // Les tabulations / retours ligne injectés dans le nom sont neutralisés
        $this->assertSame(1, substr_count($commande->commande, "\tName="));
        $this->assertStringNotContainsString("\n", $commande->commande);

        $this->depuis()->get('/iclock/getrequest?SN='.self::SN)
            ->assertOk()->assertSee("C:{$commande->id}:DATA UPDATE USERINFO PIN=12", false);
        $this->assertSame(CommandePointeuse::ENVOYEE, $commande->fresh()->statut);

        $this->depuis()->get('/iclock/getrequest?SN='.self::SN)->assertSee('OK', false);

        $this->depuis()->call('POST', '/iclock/devicecmd?SN='.self::SN, [], [], [], [], "ID={$commande->id}&Return=0&CMD=DATA\n");
        $this->assertSame(CommandePointeuse::OK, $commande->fresh()->statut);

        // Archivage : le membre est retiré de la pointeuse
        $client = Client::where('empreinte_id', '12')->firstOrFail();
        $this->delete("/clients/{$client->id}");
        $this->assertDatabaseHas('commandes_pointeuse', ['commande' => 'DATA DELETE USERINFO PIN=12', 'statut' => 'en_attente']);
    }

    public function test_nom_pour_pointeuse(): void
    {
        $this->assertSame('Kouame Ange-Marie', SynchroPointeuseService::nomPourPointeuse('Kouamé Ange-Marie'));
        $this->assertSame('Konan Aya', SynchroPointeuseService::nomPourPointeuse("Konan\tAya"));
        $this->assertSame('Membre', SynchroPointeuseService::nomPourPointeuse("\t\n"));
        $this->assertSame(24, mb_strlen(SynchroPointeuseService::nomPourPointeuse(str_repeat('A', 60))));
    }

    public function test_page_pointeuses_et_synchronisation(): void
    {
        Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Un', 'empreinte_id' => '1']);
        Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Deux', 'empreinte_id' => '2']);

        $this->actingAs($this->admin)->get('/admin/pointeuses')->assertOk()->assertSee(self::SN);
        $this->post("/admin/pointeuses/{$this->pointeuse->id}/synchroniser")->assertSessionHas('succes');
        $this->assertSame(2, CommandePointeuse::count());

        $this->post('/admin/pointeuses', ['nom' => 'Sortie', 'numero_serie' => 'ABC<script>'])->assertSessionHasErrors('numero_serie');
        $this->post('/admin/pointeuses', ['nom' => 'Sortie', 'numero_serie' => 'BKXY0001'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('lecteurs', ['numero_serie' => 'BKXY0001', 'actif' => true]);
    }
}
