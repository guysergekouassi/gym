<?php

namespace App\Http\Controllers;

use App\Models\CommandePointeuse;
use App\Models\Lecteur;
use App\Services\PointageService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Protocole « Cloud / ADMS » (iclock) des pointeuses ZKTeco.
 *
 * La pointeuse appelle elle-même ces adresses (réglées dans son menu
 * Communication → Serveur Cloud) : elle envoie chaque pointage en temps réel
 * et vient chercher les commandes en attente (ajout / retrait de membres).
 *
 * Sécurité : la pointeuse ne sait pas envoyer de mot de passe. Elle est donc
 * reconnue par son n° de série (déclaré par l'admin) ET son adresse IP
 * (mémorisée au premier contact, puis exigée). Le serveur doit rester sur le
 * réseau local de la salle, jamais exposé sur internet.
 */
class PointeuseController extends Controller
{
    private const TAILLE_MAX_OCTETS = 512 * 1024;

    private const LIGNES_MAX = 5000;

    /** GET /iclock/cdata : la pointeuse se présente et demande sa configuration. */
    public function configuration(Request $request): Response
    {
        $lecteur = $this->identifier($request);
        if (! $lecteur) {
            return $this->texte('Appareil non autorisé', 403);
        }

        $sn = $lecteur->numero_serie;
        $stamp = $lecteur->stamp_pointages ?: '9999';

        return $this->texte(implode("\n", [
            "GET OPTION FROM: {$sn}",
            "ATTLOGStamp={$stamp}",
            'OPERLOGStamp=9999',
            'ATTPHOTOStamp=None',
            'ErrorDelay=30',
            'Delay=5',
            'TransTimes=00:00;14:05',
            'TransInterval=1',
            'TransFlag=TransData AttLog OpLog EnrollUser ChgUser',
            'TimeZone=0',
            'Realtime=1',
            'Encrypt=None',
        ]));
    }

    /** POST /iclock/cdata?table=ATTLOG : pointages (n° membre + heure). */
    public function recevoir(Request $request, PointageService $pointage): Response
    {
        $lecteur = $this->identifier($request);
        if (! $lecteur) {
            return $this->texte('Appareil non autorisé', 403);
        }

        $contenu = (string) $request->getContent();
        if (strlen($contenu) > self::TAILLE_MAX_OCTETS) {
            return $this->texte('Trop volumineux', 413);
        }

        $lignes = array_slice(preg_split('/\r\n|\n|\r/', trim($contenu)) ?: [], 0, self::LIGNES_MAX);
        $table = strtoupper((string) $request->query('table'));

        if ($table !== 'ATTLOG') {
            // OPERLOG (journal d'opérations, enrôlements…) : accusé de réception seulement.
            // Les gabarits d'empreinte ne sont JAMAIS stockés par l'application.
            return $this->texte('OK: '.count(array_filter($lignes)));
        }

        $traites = 0;
        foreach ($lignes as $ligne) {
            $champs = explode("\t", trim($ligne));
            $pin = $champs[0] ?? '';
            $quand = $this->dateValide($champs[1] ?? '');

            if (! preg_match('/^[1-9][0-9]{0,8}$/', $pin) || ! $quand) {
                continue; // ligne illisible : ignorée
            }

            try {
                $pointage->parEmpreinte($pin, $lecteur, null, $quand);
                $traites++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        if (preg_match('/^[0-9]{1,30}$/', (string) $request->query('Stamp'))) {
            $lecteur->forceFill(['stamp_pointages' => $request->query('Stamp')])->save();
        }

        return $this->texte('OK: '.$traites);
    }

    /** GET /iclock/getrequest : la pointeuse vient chercher les commandes en attente. */
    public function commandes(Request $request): Response
    {
        $lecteur = $this->identifier($request);
        if (! $lecteur) {
            return $this->texte('Appareil non autorisé', 403);
        }

        $commandes = $lecteur->commandes()
            ->where('statut', CommandePointeuse::EN_ATTENTE)
            ->orderBy('id')->limit(20)->get();

        if ($commandes->isEmpty()) {
            return $this->texte('OK');
        }

        $commandes->each->update(['statut' => CommandePointeuse::ENVOYEE, 'envoyee_le' => now()]);

        return $this->texte($commandes->map(fn ($c) => "C:{$c->id}:{$c->commande}")->implode("\n"));
    }

    /** POST /iclock/devicecmd : résultat des commandes exécutées (Return=0 : succès). */
    public function resultats(Request $request): Response
    {
        $lecteur = $this->identifier($request);
        if (! $lecteur) {
            return $this->texte('Appareil non autorisé', 403);
        }

        foreach (preg_split('/\r\n|\n/', trim((string) $request->getContent())) ?: [] as $ligne) {
            parse_str(trim($ligne), $retour);
            if (! isset($retour['ID']) || ! ctype_digit((string) $retour['ID'])) {
                continue;
            }

            $code = (string) ($retour['Return'] ?? '');
            $lecteur->commandes()->whereKey((int) $retour['ID'])->update([
                'statut' => $code === '0' ? CommandePointeuse::OK : CommandePointeuse::ERREUR,
                'retour' => mb_substr($code, 0, 100),
                'terminee_le' => now(),
            ]);
        }

        return $this->texte('OK');
    }

    /** Reconnaît la pointeuse : n° de série déclaré + même adresse IP qu'au premier contact. */
    private function identifier(Request $request): ?Lecteur
    {
        $sn = (string) $request->query('SN');
        if (! preg_match('/^[A-Za-z0-9_-]{4,50}$/', $sn)) {
            return null;
        }

        $lecteur = Lecteur::pointeuses()->where('numero_serie', $sn)->first();
        if (! $lecteur) {
            Log::warning('Pointeuse inconnue', ['sn' => $sn, 'ip' => $request->ip()]);

            return null;
        }

        if ($lecteur->adresse_ip === null) {
            $lecteur->adresse_ip = $request->ip(); // premier contact : l'adresse est mémorisée
        } elseif ($lecteur->adresse_ip !== $request->ip()) {
            Log::warning('Pointeuse : adresse IP inattendue', ['sn' => $sn, 'ip' => $request->ip(), 'attendue' => $lecteur->adresse_ip]);

            return null;
        }

        $lecteur->derniere_activite_at = now();
        $lecteur->save();

        return $lecteur;
    }

    private function dateValide(string $valeur): ?Carbon
    {
        try {
            $date = Carbon::createFromFormat('Y-m-d H:i:s', trim($valeur));
        } catch (Throwable) {
            return null;
        }

        // Horloge de la pointeuse déréglée ou donnée absurde : refusée
        return $date && $date->between(now()->subDays(60), now()->addDay()) ? $date : null;
    }

    private function texte(string $corps, int $statut = 200): Response
    {
        return response($corps, $statut, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
