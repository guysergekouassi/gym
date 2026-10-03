<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\PointageService;
use App\Support\Empreinte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** API générique (agent local ou autre appareil) : authentifiée par token. */
class PointageController extends Controller
{
    /**
     * POST /api/pointage/empreinte
     * Header : Authorization: Bearer <token>
     * Body   : { "empreinte_id": "12" }
     */
    public function empreinte(Request $request, PointageService $pointage): JsonResponse
    {
        $data = $request->validate([
            'empreinte_id' => ['required', 'string', Empreinte::REGLE],
        ]);

        $passage = $pointage->parEmpreinte($data['empreinte_id'], $request->attributes->get('lecteur'));
        $passage->load('client');

        $client = $passage->client;
        $abonnement = $client?->abonnementActif();
        $finDroits = $client?->finDesDroits();

        return response()->json([
            'autorise' => $passage->estAutorise(),
            'motif' => $passage->motif,
            'message' => $passage->message(),
            'passage_id' => $passage->id,
            'client' => $client ? [
                'id' => $client->id,
                'nom' => $client->nom_complet,
                'type' => Client::TYPES[$client->type] ?? $client->type,
            ] : null,
            'abonnement' => $abonnement ? [
                'formule' => $abonnement->formule->nom,
                'fin_droits' => $finDroits?->format('Y-m-d'),
                'jours_restants' => $finDroits ? max(0, (int) today()->diffInDays($finDroits, false)) : 0,
            ] : null,
        ]);
    }
}
