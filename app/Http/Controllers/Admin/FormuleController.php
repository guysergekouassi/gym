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
            'tarifFidelite' => Parametre::tarifFidelite(),
            'carnetMin' => (int) config('salle.carnet_min_seances'),
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

    /** Une formule déjà vendue reste dans l'historique : on la désactive au lieu de la supprimer. */
    public function destroy(Formule $formule): RedirectResponse
    {
        if ($formule->abonnements()->exists()) {
            return back()->with('erreur', "« {$formule->nom} » a déjà été vendue : décochez « Active » pour la retirer de la caisse.");
        }

        $formule->delete();

        return back()->with('succes', "Formule « {$formule->nom} » supprimée.");
    }

    public function tarifJournalier(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tarif_journalier' => ['required', 'integer', 'min:0', 'max:1000000'],
            'tarif_fidelite' => ['sometimes', 'required', 'integer', 'min:0', 'max:1000000'],
        ]);

        Parametre::definir(Parametre::TARIF_JOURNALIER, $data['tarif_journalier']);
        if (isset($data['tarif_fidelite'])) {
            Parametre::definir(Parametre::TARIF_FIDELITE, $data['tarif_fidelite']);
        }

        return back()->with('succes', 'Tarifs des séances mis à jour.');
    }

    private function valider(Request $request, ?Formule $formule = null): array
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:100', Rule::unique('formules', 'nom')->ignore($formule?->id)],
            'duree_jours' => ['required', 'integer', 'min:1', 'max:730'],
            'prix' => ['required', 'integer', 'min:0', 'max:10000000'],
            'seances_par_jour' => ['nullable', 'integer', 'in:1'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        // Champ vide = accès illimité / aucun avantage
        $data['seances_par_jour'] = $data['seances_par_jour'] ?? null;
        $data['description'] = trim((string) ($data['description'] ?? '')) ?: null;

        return $data;
    }
}
