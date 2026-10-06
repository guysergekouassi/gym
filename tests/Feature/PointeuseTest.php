<?php

namespace Tests\Feature;

use App\Models\Abonnement;
use App\Models\Client;
use App\Models\CommandePointeuse;
use App\Models\Formule;
use App\Models\Lecteur;
use App\Models\Passage;
use App\Models\User;
use App\Services\PointeuseService;
use App\Services\SynchroPointeuseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as RequeteHttp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Liaison avec la pointeuse Hikvision (API ISAPI), simulée. */
class PointeuseTest extends TestCase
{
    use RefreshDatabase;

    private Lecteur $pointeuse;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->seed();
        User::query()->update(['doit_changer_mdp' => false]);
        $this->admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();

        $this->actingAs($this->admin)->post('/admin/pointeuses', [
            'nom' => 'Entrée', 'adresse_ip' => '192.168.50.64', 'port' => 80, 'identifiant' => 'admin', 'mot_de_passe' => 'Secret.Pointeuse1',
        ])->assertSessionHasNoErrors();
        $this->pointeuse = Lecteur::where('adresse_ip', '192.168.50.64')->firstOrFail();
    }

    /** Comme une vraie pointeuse : défi Digest (401) puis réponse une fois authentifié. */
    private function pointeuse(array $reponses): void
    {
        Http::fake(function (RequeteHttp $r) use ($reponses) {
            if (! str_contains((string) ($r->header('Authorization')[0] ?? ''), 'Digest')) {
                return Http::response('', 401, ['WWW-Authenticate' => 'Digest realm="DS-K1T808", qop="auth", nonce="abc123", opaque="xyz"']);
            }
            foreach ($reponses as $motif => $reponse) {
                if (str_contains($r->url(), $motif)) {
                    return $reponse;
                }
            }

            return Http::response('', 404);
        });
    }

    private function evenement(?string $no, int $minor, string $quand): array
    {
        return array_filter(['major' => 5, 'minor' => $minor, 'time' => $quand, 'employeeNoString' => $no], fn ($v) => $v !== null);
    }

    public function test_mot_de_passe_chiffre_et_adresses_refusees(): void
    {
        $brut = DB::table('lecteurs')->where('id', $this->pointeuse->id)->value('mot_de_passe');
        $this->assertNotSame('Secret.Pointeuse1', $brut);
        $this->assertSame('Secret.Pointeuse1', $this->pointeuse->mot_de_passe);
        $this->assertArrayNotHasKey('mot_de_passe', $this->pointeuse->toArray());

        // Seules les adresses du réseau local sont acceptées (pas internet, pas la machine elle-même)
        foreach (['8.8.8.8', '127.0.0.1', '169.254.1.1', 'pointeuse.exemple.com', '192.168.1'] as $ip) {
            $this->post('/admin/pointeuses', ['nom' => 'X', 'adresse_ip' => $ip, 'port' => 80, 'identifiant' => 'admin', 'mot_de_passe' => 'x'])
                ->assertSessionHasErrors('adresse_ip');
        }

        $caissiere = User::where('role', User::ROLE_CAISSIER)->firstOrFail();
        $this->actingAs($caissiere)->get('/admin/pointeuses')->assertForbidden();
    }

    public function test_tester_la_connexion(): void
    {
        $this->pointeuse(['/ISAPI/System/deviceInfo' => Http::response(
            '<?xml version="1.0" encoding="UTF-8"?><DeviceInfo xmlns="http://www.isapi.org/ver20/XMLSchema"><deviceName>Access Controller</deviceName><model>DS-K1T808MFWX</model><serialNumber>DS-K1T808MFWX20240101AAWRQC0292495</serialNumber><firmwareVersion>V1.0.4</firmwareVersion></DeviceInfo>'
        )]);

        $this->post("/admin/pointeuses/{$this->pointeuse->id}/tester")->assertSessionHas('succes');
        $this->pointeuse->refresh();
        $this->assertSame('DS-K1T808MFWX', $this->pointeuse->modele);
        $this->assertTrue($this->pointeuse->estEnLigne());

        Http::assertSent(fn (RequeteHttp $r) => str_starts_with($r->url(), 'http://192.168.50.64/ISAPI/'));
    }

    public function test_pointeuse_injoignable_ou_mauvais_mot_de_passe(): void
    {
        Http::fake(['*' => Http::response('', 401, ['WWW-Authenticate' => 'Digest realm="x", nonce="n", qop="auth"'])]);
        $this->post("/admin/pointeuses/{$this->pointeuse->id}/tester")->assertSessionHas('erreur', 'Identifiant ou mot de passe de la pointeuse refusé.');

        Http::fake(['*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout')]);
        app(PointeuseService::class)->cycle($this->pointeuse->refresh());
        $this->assertStringContainsString('injoignable', $this->pointeuse->refresh()->derniere_erreur);
    }

    public function test_passages_recuperes_et_dedoublonnes(): void
    {
        $abonne = Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Yao', 'empreinte_id' => '7']);
        Abonnement::create([
            'client_id' => $abonne->id, 'formule_id' => Formule::where('nom', 'Passe mensuelle Illimitée')->value('id'), 'date_debut' => today(),
            'date_fin' => today()->addDays(29), 'montant' => 15000, 'statut' => Abonnement::STATUT_ACTIF,
        ]);
        Client::create(['type' => Client::TYPE_ABONNE, 'nom' => 'Expiré', 'empreinte_id' => '8']);
        CommandePointeuse::query()->delete();

        $t = now()->subSeconds(30)->format('Y-m-d\TH:i:sP');
        $this->pointeuse(['/ISAPI/AccessControl/AcsEvent' => Http::response(['AcsEvent' => [
            'responseStatusStrg' => 'OK', 'numOfMatches' => 5,
            'InfoList' => [
                $this->evenement('7', 38, $t),        // empreinte reconnue
                $this->evenement('8', 6, $t),         // reconnu mais refusé par la pointeuse (abonnement fini)
                $this->evenement(null, 40, $t),       // doigt inconnu
                $this->evenement(null, 21, $t),       // porte ouverte : ignoré
                $this->evenement("1' OR 1=1", 38, $t), // numéro invalide : ignoré
                $this->evenement('900000001', 38, $t), // employé (ouvre le menu) : ignoré
            ],
        ]])]);

        $service = app(PointeuseService::class);
        $service->cycle($this->pointeuse);
        $service->cycle($this->pointeuse->refresh()); // même lot renvoyé : pas de doublon

        $this->assertSame(3, Passage::count());
        $this->assertDatabaseHas('passages', ['client_id' => $abonne->id, 'statut' => 'autorise', 'lecteur_id' => $this->pointeuse->id]);
        $this->assertDatabaseHas('passages', ['empreinte_id' => '8', 'statut' => 'refuse', 'motif' => 'abonnement_expire']);
        $this->assertDatabaseMissing('passages', ['empreinte_id' => '900000001']);
        $this->assertDatabaseHas('passages', ['empreinte_id' => null, 'motif' => 'empreinte_inconnue']);
        $this->assertNull($this->pointeuse->refresh()->derniere_erreur);
        $this->assertNotNull($this->pointeuse->dernier_evenement_le);

        $this->getJson('/accueil/dernier')->assertOk();
    }

    public function test_membres_et_dates_de_fin_envoyes_a_la_pointeuse(): void
    {
        $this->pointeuse(['/ISAPI/' => Http::response(['statusCode' => 1, 'statusString' => 'OK'])]);

        $this->post('/clients', ['type' => Client::TYPE_ABONNE, 'nom' => "Kon\tan", 'prenoms' => 'Aya', 'empreinte_id' => '12'])->assertSessionHasNoErrors();
        $client = Client::where('empreinte_id', '12')->firstOrFail();
        $this->post('/caisse/abonnement', ['client_id' => $client->id, 'formule_id' => Formule::where('nom', 'Passe mensuelle Illimitée')->value('id'), 'mode' => 'especes']);

        // Une seule demande en attente par membre (la plus récente)
        $this->assertSame(1, CommandePointeuse::where('statut', 'en_attente')->count());

        app(PointeuseService::class)->executerCommandes($this->pointeuse);
        $this->assertSame(1, CommandePointeuse::where('statut', 'ok')->count());

        Http::assertSent(function (RequeteHttp $r) {
            $info = $r['UserInfo'] ?? null;

            return str_contains($r->url(), '/ISAPI/AccessControl/UserInfo/SetUp')
                && $info['employeeNo'] === '12'
                && $info['name'] === 'Kon an Aya'
                && $info['Valid']['endTime'] === today()->addDays(29)->format('Y-m-d').'T23:59:59';
        });

        // Archivage : membre retiré de la pointeuse
        $this->delete("/clients/{$client->id}");
        app(PointeuseService::class)->executerCommandes($this->pointeuse);
        Http::assertSent(fn (RequeteHttp $r) => str_contains($r->url(), 'UserInfo/Delete')
            && $r['UserInfoDelCond']['EmployeeNoList'][0]['employeeNo'] === '12');
    }

    public function test_validite_selon_le_type_de_client(): void
    {
        $service = app(PointeuseService::class);
        $journalier = Client::create(['type' => Client::TYPE_JOURNALIER, 'nom' => 'Passage', 'empreinte_id' => '20']);

        [, $fin] = $service->validite($journalier);
        $this->assertTrue($fin->lt(today()), 'Sans paiement du jour, le journalier est refusé');

        $this->post('/caisse/journalier', ['client_id' => $journalier->id, 'mode' => 'especes']);
        [, $fin] = $service->validite($journalier);
        $this->assertTrue($fin->isToday(), 'Après paiement, il entre jusqu\'à ce soir');

        $this->assertSame('Kouame Ange-Marie', SynchroPointeuseService::nomPourPointeuse('Kouamé Ange-Marie'));
    }
}
