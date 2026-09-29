<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Passage;
use App\Services\PointageService;
use App\Services\PorteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PointageController extends Controller
{
    /**
     * POST /api/pointage/empreinte
     * Header : Authorization: Bearer <token du lecteur>
     * Body   : { "empreinte_id": "123" }
     */
    public function empreinte(Request $request, PointageService $pointage, PorteService $porte): JsonResponse
    {
        $data = $request->validate(['empreinte_id' => ['required', 'string', 'max:64']]);

        return self::reponse($pointage->parEmpreinte($data['empreinte_id'], $request->attributes->get('lecteur')), $porte);
    }

    /**
     * POST /api/pointage/carte
     * Body : { "carte": "0012774155" }  (n° de badge RFID ou contenu du QR code)
     */
    public function carte(Request $request, PointageService $pointage, PorteService $porte): JsonResponse
    {
        $data = $request->validate(['carte' => ['required', 'string', 'max:64']]);

        return self::reponse($pointage->parCarte($data['carte'], $request->attributes->get('lecteur')), $porte);
    }

    public static function reponse(Passage $passage, PorteService $porte): JsonResponse
    {
        $passage->load('client');
        $client = $passage->client;
        $abonnement = $client?->abonnementActif();
        $finDroits = $client?->finDesDroits();

        return response()->json([
            'autorise' => $passage->estAutorise(),
            'ouvrir_porte' => $passage->estAutorise(),
            'motif' => $passage->motif,
            'message' => $passage->message(),
            'passage_id' => $passage->id,
            'client' => $client ? [
                'id' => $client->id,
                'nom' => $client->nom_complet,
                'type' => Client::TYPES[$client->type] ?? $client->type,
                'photo_url' => $client->photo_url,
            ] : null,
            'abonnement' => $abonnement ? [
                'formule' => $abonnement->formule->nom,
                'fin_droits' => ($finDroits ?? $abonnement->date_fin)->format('Y-m-d'),
                'jours_restants' => max(0, (int) today()->diffInDays($finDroits ?? $abonnement->date_fin, false)),
                'entrees_restantes' => $abonnement->entrees_restantes,
            ] : null,
            'porte_pilotee' => $porte->estActive(),
        ]);
    }
}
