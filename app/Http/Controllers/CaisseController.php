<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Cloture;
use App\Models\Coach;
use App\Models\Formule;
use App\Models\Paiement;
use App\Models\Produit;
use App\Services\CaisseService;
use App\Services\KpiService;
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

    public function index(Request $request, KpiService $kpi): View
    {
        $caisse = $request->user()->caisseActive();
        $client = $request->integer('client_id') ? Client::find($request->integer('client_id')) : null;
        $formules = Formule::where('actif', true)->orderBy('type')->orderBy('prix')->get();

        return view('caisse.index', [
            'caisse' => $caisse,
            'cloturee' => $caisse->estClotureeLe(),
            'resume' => $kpi->resumeCaisse($caisse),
            'aRegulariser' => $kpi->refusARegulariser(),
            'formules' => $formules->whereIn('type', [Formule::TYPE_ABONNEMENT, Formule::TYPE_CARNET])->values(),
            'formulesCoaching' => $formules->where('type', Formule::TYPE_COACHING)->values(),
            'coachs' => Coach::where('actif', true)->orderBy('nom')->get(),
            'produits' => Produit::where('actif', true)->orderBy('nom')->get(),
            'clientPreselectionne' => $client,
            'finDroitsPreselectionne' => $client?->finDesDroits(),
            'premierAbonnement' => $client ? ! $client->abonnements()->where('statut', 'actif')->exists() : true,
        ]);
    }

    public function journalier(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'nom' => ['nullable', 'string', 'max:100'],
            'telephone' => ['nullable', 'string', 'max:20'],
            'montant' => ['required', 'integer', 'min:0', 'max:1000000'],
            'mode' => ['required', Rule::in(Paiement::MODES_CAISSE)],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        return $this->apresPaiement($this->caisse->encaisserJournalier($data, $request->user()), 'Entrée journalière encaissée, accès validé.');
    }

    public function abonnement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'formule_id' => ['required', 'integer', Rule::exists('formules', 'id')->where('actif', true)->whereIn('type', [Formule::TYPE_ABONNEMENT, Formule::TYPE_CARNET])],
            'mode' => ['required', Rule::in(Paiement::MODES_CAISSE)],
            'reference' => ['nullable', 'string', 'max:100'],
            'code_promo' => ['nullable', 'string', 'max:30'],
            'frais_inscription' => ['nullable', 'boolean'],
        ]);

        $paiement = $this->caisse->souscrireAbonnement(
            Client::findOrFail($data['client_id']),
            Formule::findOrFail($data['formule_id']),
            $data,
            $request->user(),
        );

        return $this->apresPaiement($paiement, 'Abonnement enregistré.');
    }

    public function coaching(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'formule_id' => ['required', 'integer', Rule::exists('formules', 'id')->where('actif', true)->where('type', Formule::TYPE_COACHING)],
            'coach_id' => ['nullable', 'integer', 'exists:coachs,id'],
            'mode' => ['required', Rule::in(Paiement::MODES_CAISSE)],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $paiement = $this->caisse->vendreCoaching(
            Client::findOrFail($data['client_id']),
            Formule::findOrFail($data['formule_id']),
            ! empty($data['coach_id']) ? Coach::find($data['coach_id']) : null,
            $data,
            $request->user(),
        );

        return $this->apresPaiement($paiement, 'Pack de coaching vendu.');
    }

    public function vente(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'quantites' => ['required', 'array'],
            'quantites.*' => ['nullable', 'integer', 'min:0', 'max:999'],
            'mode' => ['required', Rule::in(Paiement::MODES_CAISSE)],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        return $this->apresPaiement($this->caisse->vendreProduits($data['quantites'], $data, $request->user()), 'Vente enregistrée.');
    }

    public function cloture(Request $request, KpiService $kpi): View
    {
        $caisse = $request->user()->caisseActive();

        return view('caisse.cloture', [
            'caisse' => $caisse,
            'resume' => $kpi->resumeCaisse($caisse),
            'coupures' => Cloture::COUPURES,
            'fond' => (int) config('salle.fond_caisse'),
        ]);
    }

    public function cloturer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'coupures' => ['nullable', 'array'],
            'coupures.*' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'fond_caisse' => ['required', 'integer', 'min:0', 'max:10000000'],
            'motif_ecart' => ['nullable', 'string', 'max:255'],
        ]);

        $cloture = $this->caisse->cloturer(
            $request->user()->caisseActive(),
            $request->user(),
            $data['coupures'] ?? [],
            (int) $data['fond_caisse'],
            $data['motif_ecart'] ?? null,
        );

        return redirect()->route('caisse.index')->with('succes', $cloture->ecart === 0
            ? 'Caisse clôturée : le tiroir est juste.'
            : 'Caisse clôturée avec un écart de '.number_format($cloture->ecart, 0, ',', ' ').' F, signalé à l’administrateur.');
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
                ->orWhere('telephone', 'like', "%{$q}%")
                ->orWhere('empreinte_id', $q)
                ->orWhere('carte_id', $q))
            ->orderBy('nom')
            ->limit(10)
            ->get();

        return response()->json($clients->map(fn (Client $c) => [
            'id' => $c->id,
            'nom' => $c->nom_complet,
            'type' => Client::TYPES[$c->type] ?? $c->type,
            'telephone' => $c->telephone,
            'empreinte' => $c->empreinte_id,
            'fin_droits' => $c->finDesDroits()?->format('d/m/Y'),
            'fin_droits_iso' => $c->finDesDroits()?->toDateString(),
            'premier' => ! $c->abonnements()->where('statut', 'actif')->exists(),
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
