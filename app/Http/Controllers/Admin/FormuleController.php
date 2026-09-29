<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Formule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Catalogue : abonnements, carnets d'entrées et packs de coaching. */
class FormuleController extends Controller
{
    public function index(): View
    {
        return view('admin.formules.index', [
            'formules' => Formule::withCount(['abonnements as en_cours' => fn ($q) => $q->enCours()])
                ->orderByDesc('actif')->orderBy('type')->orderBy('prix')->get()->groupBy('type'),
        ]);
    }

    public function create(): View
    {
        return view('admin.formules.form', ['formule' => new Formule(['type' => Formule::TYPE_ABONNEMENT, 'categorie' => 'Standard', 'actif' => true, 'duree_jours' => 30])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $formule = Formule::create($this->valider($request));

        return redirect()->route('admin.formules.index')->with('succes', "Formule « {$formule->nom} » créée.");
    }

    public function edit(Formule $formule): View
    {
        return view('admin.formules.form', ['formule' => $formule]);
    }

    public function update(Request $request, Formule $formule): RedirectResponse
    {
        $formule->update($this->valider($request, $formule));

        return redirect()->route('admin.formules.index')->with('succes', "Formule « {$formule->nom} » mise à jour. Les abonnements déjà vendus gardent leur prix.");
    }

    private function valider(Request $request, ?Formule $formule = null): array
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:100', Rule::unique('formules', 'nom')->ignore($formule?->id)],
            'type' => ['required', Rule::in(array_keys(Formule::TYPES))],
            'categorie' => ['nullable', 'string', 'max:50'],
            'duree_jours' => ['required', 'integer', 'min:1', 'max:1095'],
            'nb_entrees' => ['nullable', 'required_if:type,carnet', 'integer', 'min:1', 'max:500'],
            'nb_seances' => ['nullable', 'required_if:type,coaching', 'integer', 'min:1', 'max:500'],
            'prix' => ['required', 'integer', 'min:0', 'max:10000000'],
            'description' => ['nullable', 'string', 'max:255'],
        ]) + ['actif' => $request->boolean('actif')];

        if ($data['type'] !== Formule::TYPE_CARNET) {
            $data['nb_entrees'] = null;
        }
        if ($data['type'] !== Formule::TYPE_COACHING) {
            $data['nb_seances'] = null;
        }

        return $data;
    }
}