<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Passage;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/** Écran affiché à l'entrée : montre la fiche du client qui vient de scanner. */
class AccueilController extends Controller
{
    public function index(): View
    {
        return view('accueil.index');
    }

    public function dernier(): JsonResponse
    {
        $passage = Passage::with('client')->latest('passe_le')->latest('id')->first();

        if (! $passage) {
            return response()->json(null);
        }

        $client = $passage->client;
        $finDroits = $client?->finDesDroits();

        return response()->json([
            'id' => $passage->id,
            'autorise' => $passage->estAutorise(),
            'message' => $passage->message(),
            'methode' => $passage->methode,
            'heure' => $passage->passe_le->format('H:i'),
            'il_y_a_secondes' => (int) abs(now()->diffInSeconds($passage->passe_le)),
            'client' => $client ? [
                'nom' => $client->nom_complet,
                'type' => Client::TYPES[$client->type] ?? $client->type,
                'photo_url' => $client->photo_url,
            ] : null,
            'fin_droits' => $finDroits?->format('d/m/Y'),
            'jours_restants' => $finDroits ? max(0, (int) today()->diffInDays($finDroits, false)) : null,
        ]);
    }
}
