<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\PointageService;
use App\Support\Badge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PointageController extends Controller
{
    /**
     * POST /api/pointage/badge
     * Header : Authorization: Bearer <token du lecteur>
     * Body   : { "badge_id": "0012345678" }   (ancien nom accepté : empreinte_id)
     */
    public function badge(Request $request, PointageService $pointage): JsonResponse
    {
        if (! $request->filled('badge_id') && $request->filled('empreinte_id')) {
            $request->merge(['badge_id' => $request->input('empreinte_id')]);
        }

        $data = $request->validate([
            'badge_id' => ['required', 'string', Badge::REGLE],
        ]);

        $passage = $pointage->parBadge($data['badge_id'], $request->attributes->get('lecteur'));
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
