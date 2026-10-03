<?php

namespace App\Http\Controllers;

use App\Models\Abonnement;
use App\Models\Client;
use App\Models\Formule;
use App\Models\Paiement;
use App\Models\Parametre;
use App\Services\CaisseService;
use App\Services\RecuService;
use App\Support\Recherche;
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

        // La caissière ne voit que ses propres encaissements ; l'admin voit tout
        $paiementsJour = Paiement::with(['client', 'abonnement.formule', 'user:id,name'])
            ->whereDate('created_at', today()->toDateString())
            ->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))
            ->latest('id')
            ->get();

        $valides = $paiementsJour->reject->estAnnule();

        return view('caisse.index', [
            'formules' => Formule::where('actif', true)->orderBy('duree_jours')->get(),
            'tarifJournalier' => Parametre::tarifJournalier(),
            'paiements' => $paiementsJour->take(20),
            'totalJour' => $valides->sum('montant'),
            'nombreJour' => $valides->count(),
            'parMode' => $valides->groupBy('mode')->map->sum('montant')->sortDesc(),
            'onglet' => $request->integer('client_id') ? 'abonnement'
                : (in_array($request->query('onglet'), ['abonnement', 'renouvellement'], true) ? $request->query('onglet') : 'passage'),
            'aRenouveler' => $this->aRenouveler(),
            'clientPreselectionne' => $request->integer('client_id')
                ? Client::find($request->integer('client_id'))
                : null,
        ]);
    }

    public function journalier(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'nom' => ['nullable', 'string', 'max:100'],
            'telephone' => ['nullable', 'string', 'regex:/^[0-9+() .-]{6,20}$/'],
            'quantite' => ['nullable', 'integer', 'min:1', 'max:10'],
            'mode' => ['required', Rule::in(array_keys(Paiement::MODES))],
            'reference' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9 ._\/-]*$/'],
        ]);

        $paiement = $this->caisse->encaisserJournalier($data, $request->user());

        return $this->apresPaiement($paiement, 'Entrée journalière encaissée, accès validé.');
    }

    public function abonnement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'formule_id' => ['required', 'integer', Rule::exists('formules', 'id')->where('actif', true)],
            'mode' => ['required', Rule::in(array_keys(Paiement::MODES))],
            'reference' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9 ._\/-]*$/'],
        ], [
            'client_id.required' => 'Choisissez le client à abonner (recherche par nom ou téléphone).',
        ]);

        $paiement = $this->caisse->souscrireAbonnement(
            Client::findOrFail($data['client_id']),
            Formule::findOrFail($data['formule_id']),
            $data,
            $request->user(),
        );

        return $this->apresPaiement($paiement, 'Abonnement enregistré.');
    }

    /**
     * Abonnés dont la fin approche (7 jours) ou est passée depuis moins de 30 jours,
     * et qui n'ont pas déjà prolongé : la liste de l'onglet « Renouvellement ».
     */
    private function aRenouveler()
    {
        $finParClient = Abonnement::where('statut', Abonnement::STATUT_ACTIF)
            ->selectRaw('client_id, MAX(date_fin) as fin')
            ->groupBy('client_id');

        return Client::abonnes()
            ->joinSub($finParClient, 'droits', 'droits.client_id', '=', 'clients.id')
            ->whereBetween('droits.fin', [today()->subDays(30)->toDateString(), today()->addDays(7)->toDateString()])
            ->orderBy('droits.fin')
            ->limit(30)
            ->get(['clients.*', 'droits.fin']);
    }

    /** Recherche de clients pour les formulaires de caisse. */
    public function clients(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        // Sans recherche : les 20 premiers clients (ordre alphabétique) pour la liste déroulante
        $clients = Client::query()
            ->when($q !== '', fn ($query) => Recherche::appliquer($query, $q, ['nom', 'prenoms', 'telephone', 'empreinte_id']))
            ->orderBy('nom')->orderBy('prenoms')
            ->limit(20)
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

                // Le détail technique reste dans les logs, pas à l'écran
                return redirect()->route('recus.show', ['paiement' => $paiement, 'imprimer' => 1])
                    ->with('erreur', "Paiement enregistré, mais l'imprimante ne répond pas. Imprimez le reçu depuis cette page.");
            }

            return redirect()->route('caisse.index')
                ->with('succes', "{$message} Reçu {$paiement->numero_recu} imprimé.");
        }

        // Mode navigateur : la page du reçu lance l'impression automatiquement
        return redirect()->route('recus.show', ['paiement' => $paiement, 'imprimer' => 1])
            ->with('succes', $message);
    }
}
