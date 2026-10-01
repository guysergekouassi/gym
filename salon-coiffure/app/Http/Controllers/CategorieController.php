<?php

namespace App\Http\Controllers;

use App\Models\Categorie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategorieController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $categorie = Categorie::create($this->valider($request));

        return back()->with('succes', "Catégorie « {$categorie->nom} » créée.");
    }

    public function update(Request $request, Categorie $categorie): RedirectResponse
    {
        $categorie->update($this->valider($request, $categorie));

        return back()->with('succes', "Catégorie « {$categorie->nom} » mise à jour.");
    }

    public function destroy(Categorie $categorie): RedirectResponse
    {
        if ($categorie->services()->exists()) {
            return back()->with('erreur', 'Cette catégorie contient encore des services : déplacez-les ou supprimez-les d\'abord.');
        }

        $categorie->delete();

        return back()->with('succes', "Catégorie « {$categorie->nom} » supprimée.");
    }

    private function valider(Request $request, ?Categorie $categorie = null): array
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:60', Rule::unique('categories', 'nom')->ignore($categorie)],
            'couleur' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'ordre' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
        $data['ordre'] = $data['ordre'] ?? 0;

        return $data;
    }
}
