<?php

namespace App\Http\Controllers;

use App\Models\Categorie;
use App\Services\VenteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CaisseController extends Controller
{
    public function index(): View
    {
        $categories = Categorie::orderBy('ordre')->orderBy('nom')
            ->with(['services' => fn ($q) => $q->actifs()->orderBy('ordre')->orderBy('nom')])
            ->get()
            ->filter(fn (Categorie $c) => $c->services->isNotEmpty())
            ->values();

        $services = $categories->flatMap(fn (Categorie $c) => $c->services->map(fn ($s) => [
            'id' => $s->id,
            'code' => $s->code,
            'nom' => $s->nom,
            'prix' => $s->prix,
            'categorie_id' => $c->id,
            'categorie' => $c->nom,
            'couleur' => $c->couleur,
        ]))->values();

        return view('caisse.index', [
            'categories' => $categories,
            'services' => $services,
        ]);
    }

    public function store(Request $request, VenteService $ventes): JsonResponse
    {
        $data = $request->validate([
            'lignes' => ['required', 'array', 'min:1', 'max:50'],
            'lignes.*.service_id' => ['required', 'integer'],
            'lignes.*.quantite' => ['required', 'integer', 'min:1', 'max:99'],
            'mode_paiement' => ['required', Rule::in(array_keys(config('salon.modes_paiement')))],
            'montant_recu' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ], [
            'lignes.required' => 'Le ticket est vide : cliquez sur au moins un service.',
        ]);

        $vente = $ventes->enregistrer(
            $request->user(),
            $data['lignes'],
            $data['mode_paiement'],
            $data['montant_recu'] ?? null,
        );

        return response()->json([
            'numero' => $vente->numero,
            'ticket_url' => route('tickets.show', ['vente' => $vente, 'imprimer' => 1]),
        ], 201);
    }
}
