<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MouvementStock;
use App\Models\Produit;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Articles du comptoir et leur stock. */
class ProduitController extends Controller
{
    public function index(): View
    {
        return view('admin.produits.index', [
            'produits' => Produit::orderByDesc('actif')->orderBy('nom')->get(),
            'mouvements' => MouvementStock::with(['produit', 'user:id,name'])->latest()->limit(20)->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.produits.form', ['produit' => new Produit(['actif' => true, 'seuil_alerte' => 5])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->valider($request) + $request->validate(['stock' => ['required', 'integer', 'min:0', 'max:100000']]);
        $produit = Produit::create($data);
        if ($produit->stock > 0) {
            MouvementStock::create(['produit_id' => $produit->id, 'quantite' => $produit->stock, 'motif' => 'stock initial', 'user_id' => $request->user()->id]);
        }

        return redirect()->route('admin.produits.index')->with('succes', 'Article ajouté.');
    }

    public function edit(Produit $produit): View
    {
        return view('admin.produits.form', ['produit' => $produit]);
    }

    public function update(Request $request, Produit $produit): RedirectResponse
    {
        $produit->update($this->valider($request));

        return redirect()->route('admin.produits.index')->with('succes', 'Article mis à jour.');
    }

    /** Approvisionnement (+) ou correction d'inventaire (−). */
    public function stock(Request $request, Produit $produit): RedirectResponse
    {
        $data = $request->validate([
            'quantite' => ['required', 'integer', 'not_in:0', 'min:-100000', 'max:100000'],
            'motif' => ['required', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($produit, $data, $request) {
            $produit->increment('stock', $data['quantite']);
            MouvementStock::create(['produit_id' => $produit->id, 'quantite' => $data['quantite'], 'motif' => $data['motif'], 'user_id' => $request->user()->id]);
            Journal::noter('stock.mouvement', "{$produit->nom} : ".($data['quantite'] > 0 ? '+' : '').$data['quantite']." ({$data['motif']})", $produit);
        });

        return back()->with('succes', "Stock de {$produit->nom} mis à jour : {$produit->fresh()->stock}.");
    }

    private function valider(Request $request): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'prix' => ['required', 'integer', 'min:0', 'max:10000000'],
            'seuil_alerte' => ['required', 'integer', 'min:0', 'max:100000'],
        ]) + ['actif' => $request->boolean('actif')];
    }
}