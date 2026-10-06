<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Passage;
use App\Services\PointageService;
use App\Support\Empreinte;
use App\Support\Horaires;
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
        $passage = Passage::with('client')->latest('passe_le')->latest('id')->first();

        return response()->json($passage ? $this->presenter($passage) : null);
    }

    /**
     * Secours si la pointeuse est en panne : la caissière tape le n° du membre
     * puis Entrée sur l'écran d'accueil.
     */
    public function scan(Request $request, PointageService $pointage): JsonResponse
    {
        $data = $request->validate([
            'empreinte_id' => ['required', 'string', Empreinte::REGLE],
        ]);

        $passage = $pointage->parEmpreinte($data['empreinte_id'], null, $request->user());
        $passage->load('client');

        return response()->json($this->presenter($passage));
    }

    private function presenter(Passage $passage): array
    {
        $client = $passage->client;
        $finDroits = $client?->finDesDroits();

        return [
            'id' => $passage->id,
            'autorise' => $passage->estAutorise(),
            'message' => $passage->message(),
            'methode' => $passage->methode,
            'sens' => $passage->sens,
            'detail' => $this->detail($passage),
            'heure' => $passage->passe_le->format('H:i:s'),
            'il_y_a_secondes' => (int) abs(now()->diffInSeconds($passage->passe_le)),
            'client' => $client ? [
                'nom' => $client->nom_complet,
                'initiale' => mb_strtoupper(mb_substr($client->nom, 0, 1)),
                'type' => Client::TYPES[$client->type] ?? $client->type,
                'photo_url' => $client->photo_url,
            ] : null,
            'fin_droits' => $finDroits?->format('d/m/Y'),
            'jours_restants' => $finDroits ? max(0, (int) today()->diffInDays($finDroits, false)) : null,
            // Carnet Fidélité : séances restantes après ce passage (null si le client n'a jamais eu de carnet)
            'seances_carnet' => $client && ! $finDroits && $client->carnets()->isNotEmpty() ? $client->seancesCarnet() : null,
        ];
    }

    /** Badge en trop avant l'heure de fin : on indique à partir de quand le départ se badge. */
    private function detail(Passage $passage): ?string
    {
        if ($passage->sens !== Passage::SENS_DEJA) {
            return null;
        }

        $fin = Horaires::finDuJour($passage->passe_le);
        $aDejaUnDepart = Passage::where('client_id', $passage->client_id)
            ->where('sens', Passage::SENS_DEPART)
            ->whereDate('passe_le', $passage->passe_le->toDateString())
            ->exists();

        return $fin && ! $aDejaUnDepart ? "Arrivée déjà enregistrée. Le départ se badge à partir de {$fin}." : null;
    }
}
