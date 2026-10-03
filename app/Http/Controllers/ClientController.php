<?php

namespace App\Http\Controllers;

use App\Models\Abonnement;
use App\Models\Client;
use App\Models\Passage;
use App\Services\SynchroPointeuseService;
use App\Support\Empreinte;
use App\Support\Recherche;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function __construct(private SynchroPointeuseService $pointeuse) {}

    public function index(Request $request): View
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(array_keys(Client::TYPES))],
            'statut' => ['nullable', Rule::in(['en_regle', 'expire'])],
        ]);

        $jour = today()->toDateString();
        $finDroits = fn ($q) => $q->where('statut', Abonnement::STATUT_ACTIF);
        $enRegle = fn ($q) => $q->where('statut', Abonnement::STATUT_ACTIF)->whereDate('date_fin', '>=', $jour);

        $clients = Client::query()
            ->when($request->filled('q'), fn ($query) => Recherche::appliquer(
                $query, (string) $request->query('q'), ['nom', 'prenoms', 'telephone', 'empreinte_id']
            ))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->query('statut') === 'en_regle', fn ($q) => $q->whereHas('abonnements', $enRegle))
            ->when($request->query('statut') === 'expire', fn ($q) => $q->abonnes()->whereDoesntHave('abonnements', $enRegle))
            ->withMax(['passages as dernier_passage_le' => fn ($q) => $q->where('statut', Passage::STATUT_AUTORISE)], 'passe_le')
            ->withMax(['abonnements as fin_droits' => $finDroits], 'date_fin')
            ->orderBy('nom')
            ->paginate(25)
            ->withQueryString();

        return view('clients.index', [
            'clients' => $clients,
            'totaux' => [
                'tous' => Client::count(),
                'en_regle' => Client::whereHas('abonnements', $enRegle)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('clients.form', [
            'client' => new Client(['type' => Client::TYPE_ABONNE]),
            'numeroSuggere' => Empreinte::prochainNumero(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->valider($request);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $this->stockerPhoto($request->file('photo'));
        }

        $client = Client::create($data);
        $this->pointeuse->ajouterOuModifier($client);

        if ($request->input('apres') === 'abonner') {
            return redirect()->route('caisse.index', ['client_id' => $client->id])
                ->with('succes', 'Client enregistré. Choisissez maintenant sa formule.');
        }

        return redirect()->route('clients.show', $client)->with('succes', 'Client enregistré.');
    }

    public function show(Client $client): View
    {
        $client->load([
            'abonnements' => fn ($q) => $q->with('formule')->latest('date_debut'),
            'paiements' => fn ($q) => $q->latest('id')->limit(20),
        ]);

        return view('clients.show', [
            'client' => $client,
            'finDroits' => $client->finDesDroits(),
            'passages' => $client->passages()->latest('passe_le')->limit(30)->get(),
            'venues30j' => $client->passages()->where('statut', Passage::STATUT_AUTORISE)
                ->where('passe_le', '>=', now()->subDays(30))->count(),
        ]);
    }

    public function edit(Client $client): View
    {
        return view('clients.form', ['client' => $client, 'numeroSuggere' => Empreinte::prochainNumero()]);
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $data = $this->valider($request, $client);

        if ($request->hasFile('photo')) {
            if ($client->photo_path) {
                Storage::disk('public')->delete($client->photo_path);
            }
            $data['photo_path'] = $this->stockerPhoto($request->file('photo'));
        }

        $ancienNumero = $client->empreinte_id;
        $client->update($data);

        // La pointeuse suit : changement de n° → l'ancien est retiré ; nom ou n° modifié → mise à jour
        if ($ancienNumero && $ancienNumero !== $client->empreinte_id) {
            $this->pointeuse->retirer($ancienNumero);
        }
        if ($client->wasChanged(['empreinte_id', 'nom', 'prenoms'])) {
            $this->pointeuse->ajouterOuModifier($client);
        }

        return redirect()->route('clients.show', $client)->with('succes', 'Client mis à jour.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        // Le n° est libéré (et retiré de la pointeuse) pour pouvoir être réattribué
        if ($client->empreinte_id) {
            $this->pointeuse->retirer($client->empreinte_id);
        }
        $client->forceFill(['empreinte_id' => null])->save();
        $client->delete();

        return redirect()->route('clients.index')->with('succes', 'Client archivé, son empreinte est retirée de la pointeuse.');
    }

    private function stockerPhoto(UploadedFile $photo): string
    {
        // Nom aléatoire + extension déduite du contenu réel (jamais du nom fourni)
        return $photo->storeAs('clients', bin2hex(random_bytes(20)).'.'.$photo->extension(), 'public');
    }

    private function valider(Request $request, ?Client $client = null): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Client::TYPES))],
            'nom' => ['required', 'string', 'max:100'],
            'prenoms' => ['nullable', 'string', 'max:150'],
            'telephone' => ['nullable', 'string', 'regex:/^[0-9+() .-]{6,20}$/', Rule::unique('clients', 'telephone')->ignore($client?->id)],
            'email' => ['nullable', 'email', 'max:150'],
            'date_naissance' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'sexe' => ['nullable', Rule::in(['M', 'F'])],
            'empreinte_id' => ['nullable', 'string', Empreinte::REGLE, Rule::unique('clients', 'empreinte_id')->ignore($client?->id)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
        ], [
            'empreinte_id.unique' => 'Ce n° de pointeuse est déjà attribué à un autre client.',
            'telephone.unique' => 'Ce numéro de téléphone est déjà enregistré.',
            'telephone.regex' => 'Le numéro de téléphone n\'est pas valide.',
            'empreinte_id.regex' => 'Le n° de pointeuse est un nombre entier (ex. 12).',
        ]);

        unset($data['photo']);

        return $data;
    }
}
