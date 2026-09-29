<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Recherche universelle (Ctrl + K) : membres par nom, téléphone, empreinte ou carte, avec actions. */
class RechercheController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $caissier = $request->user()->isCaissier();

        if (mb_strlen($q) < 2) {
            return response()->json(['clients' => []]);
        }

        $telephone = preg_replace('/\s+/', '', $q);
        $clients = Client::query()
            ->where(fn ($w) => $w->where('nom', 'like', "%{$q}%")
                ->orWhere('prenoms', 'like', "%{$q}%")
                ->orWhereRaw("REPLACE(telephone, ' ', '') like ?", ["%{$telephone}%"])
                ->orWhere('empreinte_id', $q)
                ->orWhere('carte_id', $q))
            ->orderBy('nom')
            ->limit(8)
            ->get();

        return response()->json(['clients' => $clients->map(function (Client $c) use ($caissier) {
            $fin = $c->finDesDroits();
            $gel = $c->gelEnCours();
            $jours = $fin ? (int) today()->diffInDays($fin, false) : null;

            [$classe, $statut] = match (true) {
                (bool) $gel => ['info', 'Gelé'],
                $c->type === Client::TYPE_JOURNALIER => ['info', 'Journalier'],
                $jours === null => ['ko', 'Expiré'],
                $jours <= 7 => ['warn', $jours === 0 ? 'Expire aujourd’hui' : "Expire dans {$jours} j"],
                default => ['ok', "{$jours} j restants"],
            };

            $actions = [['libelle' => 'Fiche', 'url' => route('clients.show', $c)]];
            if ($caissier) {
                array_unshift($actions, ['libelle' => $c->type === Client::TYPE_JOURNALIER ? 'Abonner' : ($jours === null ? 'Renouveler' : 'Prolonger'), 'url' => route('caisse.index', ['client_id' => $c->id]).'#abonnement']);
            }

            return [
                'id' => $c->id,
                'nom' => $c->nom_complet,
                'initiales' => $c->initiales,
                'telephone' => $c->telephone,
                'empreinte' => $c->empreinte_id,
                'statut' => $statut,
                'classe' => $classe,
                'actions' => $actions,
            ];
        })]);
    }
}