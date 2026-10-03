<?php

namespace App\Services\Hikvision;

use App\Models\Lecteur;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Dialogue avec une pointeuse Hikvision (DS-K1T8xx…) par son API HTTP « ISAPI ».
 *
 * C'est l'application qui appelle la pointeuse : authentification Digest avec le
 * compte admin créé à l'activation de l'appareil, uniquement vers une adresse du
 * réseau local (jamais internet), sans suivre de redirection.
 */
class HikvisionClient
{
    public function __construct(private Lecteur $lecteur)
    {
        if (! self::adresseAutorisee((string) $lecteur->adresse_ip)) {
            throw new PointeuseInjoignable('Adresse IP refusée : seule une adresse du réseau local est acceptée.');
        }
    }

    /** Adresse IPv4 privée (192.168.x.x, 10.x.x.x, 172.16-31.x.x) : la pointeuse est sur le réseau de la salle. */
    public static function adresseAutorisee(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) === false
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /** Modèle, n° de série et version : sert de test de connexion. */
    public function infos(): array
    {
        $xml = $this->appel(fn (PendingRequest $h) => $h->get('/ISAPI/System/deviceInfo'))->body();
        $infos = @simplexml_load_string($xml, options: LIBXML_NONET | LIBXML_NOCDATA);

        if ($infos === false) {
            throw new PointeuseInjoignable('Réponse inattendue : est-ce bien une pointeuse Hikvision ?');
        }

        return [
            'modele' => mb_substr(trim((string) $infos->model), 0, 60) ?: null,
            'numero_serie' => mb_substr(preg_replace('/[^A-Za-z0-9_-]/', '', (string) $infos->serialNumber), 0, 50) ?: null,
            'version' => mb_substr(trim((string) $infos->firmwareVersion), 0, 40) ?: null,
        ];
    }

    /**
     * Événements de contrôle d'accès entre deux dates.
     *
     * @return list<array{employe: ?string, quand: CarbonImmutable, minor: int}>
     */
    public function evenements(CarbonInterface $du, CarbonInterface $au): array
    {
        $recherche = (string) Str::uuid();
        $position = 0;
        $evenements = [];

        for ($page = 0; $page < 50; $page++) {
            $reponse = $this->appel(fn (PendingRequest $h) => $h->post('/ISAPI/AccessControl/AcsEvent?format=json', [
                'AcsEventCond' => [
                    'searchID' => $recherche,
                    'searchResultPosition' => $position,
                    'maxResults' => 30,
                    'major' => 5,
                    'minor' => 0,
                    'startTime' => $du->format('Y-m-d\TH:i:sP'),
                    'endTime' => $au->format('Y-m-d\TH:i:sP'),
                ],
            ]))->json('AcsEvent') ?? [];

            foreach ((array) ($reponse['InfoList'] ?? []) as $info) {
                $quand = $this->date($info['time'] ?? null);
                if (! $quand) {
                    continue;
                }
                $employe = (string) ($info['employeeNoString'] ?? $info['employeeNo'] ?? '');
                $evenements[] = [
                    'employe' => preg_match('/^[1-9][0-9]{0,8}$/', $employe) ? $employe : null,
                    'quand' => $quand,
                    'minor' => (int) ($info['minor'] ?? 0),
                ];
            }

            $nombre = (int) ($reponse['numOfMatches'] ?? 0);
            if (($reponse['responseStatusStrg'] ?? '') !== 'MORE' || $nombre === 0) {
                break;
            }
            $position += $nombre;
        }

        return $evenements;
    }

    /**
     * Crée ou met à jour un membre. La période de validité fait que la pointeuse
     * refuse d'elle-même un membre dont l'abonnement est terminé.
     */
    public function enregistrerUtilisateur(string $numero, string $nom, CarbonInterface $debut, CarbonInterface $fin): void
    {
        $utilisateur = ['UserInfo' => [
            'employeeNo' => $numero,
            'name' => $nom,
            'userType' => 'normal',
            'Valid' => [
                'enable' => true,
                'beginTime' => $debut->format('Y-m-d\TH:i:s'),
                'endTime' => $fin->format('Y-m-d\TH:i:s'),
                'timeType' => 'local',
            ],
            'doorRight' => '1',
            'RightPlan' => [['doorNo' => 1, 'planTemplateNo' => '1']],
        ]];

        // SetUp = créer ou modifier ; les firmwares plus anciens n'ont que Modify / Record
        $reponse = $this->appel(fn (PendingRequest $h) => $h->put('/ISAPI/AccessControl/UserInfo/SetUp?format=json', $utilisateur), false);
        if ($reponse->successful()) {
            return;
        }

        $reponse = $this->appel(fn (PendingRequest $h) => $h->put('/ISAPI/AccessControl/UserInfo/Modify?format=json', $utilisateur), false);
        if ($reponse->successful()) {
            return;
        }

        $this->appel(fn (PendingRequest $h) => $h->post('/ISAPI/AccessControl/UserInfo/Record?format=json', $utilisateur));
    }

    public function supprimerUtilisateur(string $numero): void
    {
        $this->appel(fn (PendingRequest $h) => $h->put('/ISAPI/AccessControl/UserInfo/Delete?format=json', [
            'UserInfoDelCond' => ['EmployeeNoList' => [['employeeNo' => $numero]]],
        ]));
    }

    private function appel(callable $requete, bool $exigerSucces = true): Response
    {
        $http = Http::baseUrl("http://{$this->lecteur->adresse_ip}:{$this->lecteur->port}")
            ->withDigestAuth((string) $this->lecteur->identifiant, (string) $this->lecteur->mot_de_passe)
            // Jamais de redirection ni de proxy (même hérité de l'environnement) : la pointeuse est sur le réseau local
            ->withOptions(['allow_redirects' => false, 'proxy' => ['http' => null, 'https' => null, 'no' => ['*']]])
            ->connectTimeout(3)
            ->timeout(10)
            ->acceptJson();

        try {
            $reponse = $requete($http);
        } catch (ConnectionException) {
            throw new PointeuseInjoignable("Pointeuse injoignable à l'adresse {$this->lecteur->adresse_ip} : vérifiez le câble, l'alimentation et l'adresse IP.");
        }

        if ($reponse->status() === 401) {
            throw new PointeuseInjoignable('Identifiant ou mot de passe de la pointeuse refusé.');
        }
        if ($exigerSucces && ! $reponse->successful()) {
            throw new PointeuseInjoignable("La pointeuse a refusé la demande (code {$reponse->status()}).");
        }

        return $reponse;
    }

    private function date(mixed $valeur): ?CarbonImmutable
    {
        if (! is_string($valeur) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $valeur)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($valeur)->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }
}
