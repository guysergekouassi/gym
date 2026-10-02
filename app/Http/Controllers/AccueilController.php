<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Passage;
use App\Services\PointageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Écran affiché à l'entrée : montre la fiche du client qui vient de pointer. */
class AccueilController extends Controller
{
    public function index(): View
    {
        return view('accueil.index');
    }

    public function dernier(): JsonResponse
    {
        $entree = Passage::with('client')->latest('passe_le')->latest('id')->first();
        $sortie = Passage::with('client')->whereNotNull('sorti_le')->latest('sorti_le')->first();

        // Le dernier évènement peut être une sortie (passage existant mis à jour)
        $passage = $sortie && $entree && $sortie->sorti_le->gt($entree->passe_le) ? $sortie : $entree;

        return response()->json($passage ? $this->presenter($passage) : null);
    }

    /** Badge RFID ou QR code lu par un lecteur USB branché sur le poste d'accueil. */
    public function badge(Request $request, PointageService $pointage): JsonResponse
    {
        $data = $request->validate(['carte' => ['required', 'string', 'max:64']]);
        $salleId = $request->user()->caisse?->salle_id;

        return response()->json($this->presenter($pointage->parCarte($data['carte'], null, $salleId)->load('client')));
    }

    private function presenter(Passage $passage): array
    {
        $client = $passage->client;
        $finDroits = $client?->finDesDroits();
        $carnet = $client?->abonnementActif();

        return [
            'id' => $passage->id.($passage->estSortie() ? '-sortie' : ''),
            'autorise' => $passage->estAutorise(),
            'sortie' => $passage->estSortie(),
            'message' => $passage->message(),
            'methode' => $passage->methode,
            'heure' => $passage->passe_le->format('H:i'),
            'il_y_a_secondes' => (int) abs(now()->diffInSeconds($passage->sorti_le ?? $passage->passe_le)),
            'client' => $client ? [
                'nom' => $client->nom_complet,
                'prenom' => $client->appel,
                'initiales' => $client->initiales,
                'type' => Client::TYPES[$client->type] ?? $client->type,
                'photo_url' => $client->photo_url,
            ] : null,
            'formule' => $carnet?->formule?->nom,
            'fin_droits' => $finDroits?->format('d/m/Y'),
            'jours_restants' => $finDroits ? max(0, (int) today()->diffInDays($finDroits, false)) : null,
            'entrees_restantes' => $carnet?->entrees_restantes,
        ];
    }
}
