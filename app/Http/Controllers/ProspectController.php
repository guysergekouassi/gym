<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Prospect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Prospects et séances d'essai : du premier contact à l'inscription. */
class ProspectController extends Controller
{
    public function index(Request $request): View
    {
        $statut = $request->string('statut')->value();
        $tous = Prospect::with('user:id,name')->latest()->get();

        $convertibles = $tous->whereIn('statut', ['inscrit', 'perdu'])->count();

        return view('prospects.index', [
            'statut' => $statut,
            'prospects' => $statut ? $tous->where('statut', $statut)->values() : $tous->whereNotIn('statut', ['inscrit', 'perdu'])->values(),
            'compte' => $tous->countBy('statut'),
            'conversion' => $convertibles ? (int) round(100 * $tous->where('statut', 'inscrit')->count() / $convertibles) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Prospect::create($this->valider($request) + ['user_id' => $request->user()->id]);

        return back()->with('succes', 'Prospect ajouté.');
    }

    public function update(Request $request, Prospect $prospect): RedirectResponse
    {
        $prospect->update($this->valider($request));

        return back()->with('succes', 'Prospect mis à jour.');
    }

    /** Crée la fiche client à partir du prospect et ouvre le formulaire pour la compléter. */
    public function convertir(Prospect $prospect): RedirectResponse
    {
        $client = $prospect->telephone ? Client::where('telephone', $prospect->telephone)->first() : null;
        $client ??= Client::create(['type' => Client::TYPE_ABONNE, 'nom' => $prospect->nom, 'telephone' => $prospect->telephone]);
        $prospect->update(['statut' => 'inscrit', 'client_id' => $client->id]);

        return redirect()->route('clients.edit', $client)->with('succes', 'Fiche client créée : complétez-la puis encaissez l’abonnement.');
    }

    private function valider(Request $request): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:150'],
            'telephone' => ['nullable', 'string', 'max:20'],
            'source' => ['nullable', 'string', 'max:50'],
            'statut' => ['required', Rule::in(array_keys(Prospect::STATUTS))],
            'essai_le' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}