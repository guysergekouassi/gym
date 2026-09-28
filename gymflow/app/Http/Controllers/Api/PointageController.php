<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\PointageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PointageController extends Controller
{
    /**
     * POST /api/pointage/empreinte
     * Header : Authorization: Bearer <token du lecteur>
     * Body   : { "empreinte_id": "123" }
     */
    public function empreinte(Request $request, PointageService $pointage): JsonResponse
    {
        $data = $request->validate([
            'empreinte_id' => ['required', 'string', 'max:64'],
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
                'photo_url' => $client->photo_url,
            ] : null,
            'abonnement' => $abonnement ? [
                'formule' => $abonnement->formule->nom,
                'fin_droits' => $finDroits?->format('Y-m-d'),
                'jours_restants' => $finDroits ? max(0, (int) today()->diffInDays($finDroits, false)) : 0,
            ] : null,
        ]);
    }
}
