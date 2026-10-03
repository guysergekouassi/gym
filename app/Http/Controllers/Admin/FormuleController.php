<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Formule;
use App\Models\Parametre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Formules d'abonnement et tarif du passage journalier. */
class FormuleController extends Controller
{
    public function index(): View
    {
        return view('admin.formules.index', [
            'formules' => Formule::withCount(['abonnements as abonnes_en_cours' => fn ($q) => $q->enCours()])
                ->orderByDesc('actif')->orderBy('duree_jours')->get(),
            'tarifJournalier' => Parametre::tarifJournalier(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Formule::create($this->valider($request) + ['actif' => true]);

        return back()->with('succes', 'Formule créée.');
    }

    public function update(Request $request, Formule $formule): RedirectResponse
    {
        $data = $this->valider($request, $formule);
        $data['actif'] = $request->boolean('actif');

        // Les abonnements déjà vendus gardent leur prix et leurs dates : seul l'avenir change
        $formule->update($data);

        return back()->with('succes', "Formule « {$formule->nom} » mise à jour.");
    }

    public function tarifJournalier(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tarif_journalier' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);

        Parametre::definir(Parametre::TARIF_JOURNALIER, $data['tarif_journalier']);

        return back()->with('succes', 'Tarif du passage journalier mis à jour.');
    }

    private function valider(Request $request, ?Formule $formule = null): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:100', Rule::unique('formules', 'nom')->ignore($formule?->id)],
            'duree_jours' => ['required', 'integer', 'min:1', 'max:730'],
            'prix' => ['required', 'integer', 'min:0', 'max:10000000'],
        ]);
    }
}
