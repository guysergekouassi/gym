<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Formule;
use App\Models\Paiement;
use App\Services\CaisseService;
use App\Services\RecuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class CaisseController extends Controller
{
    public function __construct(
        private CaisseService $caisse,
        private RecuService $recus,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $paiementsJour = Paiement::with(['client', 'abonnement.formule'])
            ->whereDate('created_at', today()->toDateString())
            ->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))
            ->latest()
            ->get();

        return view('caisse.index', [
            'formules' => Formule::where('actif', true)->orderBy('duree_jours')->get(),
            'paiements' => $paiementsJour->take(15),
            'totalJour' => $paiementsJour->sum('montant'),
            'clientPreselectionne' => $request->integer('client_id')
                ? Client::find($request->integer('client_id'))
                : null,
        ]);
    }

    public function journalier(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'nom' => ['nullable', 'string', 'max:100'],
            'telephone' => ['nullable', 'string', 'max:20'],
            'montant' => ['required', 'integer', 'min:0', 'max:1000000'],
            'mode' => ['required', Rule::in(array_keys(Paiement::MODES))],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $paiement = $this->caisse->encaisserJournalier($data, $request->user());

        return $this->apresPaiement($paiement, 'Entrée journalière encaissée, accès validé.');
    }

    public function abonnement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'formule_id' => ['required', 'integer', Rule::exists('formules', 'id')->where('actif', true)],
            'mode' => ['required', Rule::in(array_keys(Paiement::MODES))],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $paiement = $this->caisse->souscrireAbonnement(
            Client::findOrFail($data['client_id']),
            Formule::findOrFail($data['formule_id']),
            $data,
            $request->user(),
        );

        return $this->apresPaiement($paiement, 'Abonnement enregistré.');
    }

    /** Recherche de clients pour les formulaires de caisse. */
    public function clients(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $clients = Client::query()
            ->where(fn ($w) => $w->where('nom', 'like', "%{$q}%")
                ->orWhere('prenoms', 'like', "%{$q}%")
                ->orWhere('telephone', 'like', "%{$q}%"))
            ->orderBy('nom')
            ->limit(10)
            ->get();

        return response()->json($clients->map(fn (Client $c) => [
            'id' => $c->id,
            'nom' => $c->nom_complet,
            'type' => Client::TYPES[$c->type] ?? $c->type,
            'telephone' => $c->telephone,
            'fin_droits' => $c->finDesDroits()?->format('d/m/Y'),
        ]));
    }

    private function apresPaiement(Paiement $paiement, string $message): RedirectResponse
    {
        if (config('salle.impression.driver') === 'escpos') {
            try {
                $this->recus->imprimer($paiement);
            } catch (Throwable $e) {
                report($e);

                return redirect()->route('recus.show', ['paiement' => $paiement, 'imprimer' => 1])
                    ->with('erreur', "Paiement enregistré mais l'impression a échoué : {$e->getMessage()}");
            }

            return redirect()->route('caisse.index')
                ->with('succes', "{$message} Reçu {$paiement->numero_recu} imprimé.");
        }

        // Mode navigateur : la page du reçu lance l'impression automatiquement
        return redirect()->route('recus.show', ['paiement' => $paiement, 'imprimer' => 1])
            ->with('succes', $message);
    }
}
