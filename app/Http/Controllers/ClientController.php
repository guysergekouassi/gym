<?php

namespace App\Http\Controllers;

use App\Models\Abonnement;
use App\Models\Client;
use App\Models\Passage;
use App\Services\KpiService;
use App\Services\SynchroPointeuseService;
use App\Support\Empreinte;
use App\Support\Recherche;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
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
            'statut' => ['nullable', Rule::in(['en_regle', 'expire', 'expire_bientot', 'a_relancer', 'masques'])],
        ]);

        $jour = today()->toDateString();
        $enRegle = fn ($q) => $q->where('statut', Abonnement::STATUT_ACTIF)->whereDate('date_fin', '>=', $jour);

        // Formule de l'abonnement le plus récent (affichée dans la liste)
        $formuleActuelle = Abonnement::query()
            ->join('formules', 'formules.id', '=', 'abonnements.formule_id')
            ->whereColumn('abonnements.client_id', 'clients.id')
            ->where('abonnements.statut', Abonnement::STATUT_ACTIF)
            ->orderByDesc('abonnements.date_fin')
            ->limit(1)
            ->select('formules.nom');

        $masques = $request->query('statut') === 'masques' && $request->user()->isAdmin();

        $clients = Client::query()
            ->when($masques, fn ($q) => $q->onlyTrashed())
            ->select('clients.*')
            ->addSelect(['formule_actuelle' => $formuleActuelle])
            ->when($request->filled('q'), fn ($query) => Recherche::appliquer(
                $query, (string) $request->query('q'), ['nom', 'prenoms', 'telephone', 'empreinte_id']
            ))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->query('statut') === 'en_regle', fn ($q) => $q->whereHas('abonnements', $enRegle))
            ->when($request->query('statut') === 'expire', fn ($q) => $q->abonnes()->whereDoesntHave('abonnements', $enRegle))
            ->when($request->query('statut') === 'expire_bientot', fn ($q) => $q->whereIn(
                'id', app(KpiService::class)->expirantBientot()->pluck('client_id')
            ))
            ->when($request->query('statut') === 'a_relancer', fn ($q) => $q->whereIn(
                'id', app(KpiService::class)->abonnesMoinsActifs()->pluck('id')
            ))
            ->withMax(['passages as dernier_passage_le' => fn ($q) => $q->where('statut', Passage::STATUT_AUTORISE)], 'passe_le')
            ->withMax(['abonnements as fin_droits' => fn ($q) => $q->where('statut', Abonnement::STATUT_ACTIF)], 'date_fin')
            ->orderBy('nom')
            ->paginate(25)
            ->withQueryString();

        return view('clients.index', [
            'clients' => $clients,
            'compteurs' => [
                'tous' => Client::count(),
                Client::TYPE_ABONNE => Client::abonnes()->count(),
                Client::TYPE_JOURNALIER => Client::journaliers()->count(),
            ],
            'numeroSuggere' => Empreinte::prochainNumero(),
            'masques' => $masques,
            'nombreMasques' => $request->user()->isAdmin() ? Client::onlyTrashed()->count() : 0,
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

        $data['date_adhesion'] ??= today(); // par défaut : inscrit aujourd'hui
        $client = Client::create($data);
        $this->pointeuse->ajouterOuModifier($client);

        if ($request->boolean('abonner') || $request->input('apres') === 'abonner') {
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

        $data['date_adhesion'] ??= $client->date_adhesion ?? today();
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

        return redirect()->route('clients.index')->with('succes', "{$client->nom_complet} est masqué(e) : retiré(e) de la liste et de la pointeuse. Vous pouvez le réafficher depuis « Statut : masqués ».");
    }

    /** Réafficher un client masqué (son n° de pointeuse est à réattribuer). */
    public function restaurer(int $id): RedirectResponse
    {
        $client = Client::onlyTrashed()->findOrFail($id);
        $client->restore();

        return redirect()->route('clients.show', $client)
            ->with('succes', "{$client->nom_complet} est de nouveau visible. Attribuez-lui un n° de pointeuse si besoin (Modifier).");
    }

    /**
     * Suppression définitive : seulement pour une fiche sans aucun paiement
     * (doublon, erreur de saisie). Un client qui a payé reste dans l'historique de caisse : on le masque.
     */
    public function supprimerDefinitivement(int $id): RedirectResponse
    {
        $client = Client::withTrashed()->findOrFail($id);

        if ($client->paiements()->exists()) {
            return back()->with('erreur', "{$client->nom_complet} a des paiements enregistrés : on ne peut pas l'effacer sans fausser la caisse. Masquez-le à la place.");
        }

        if ($client->empreinte_id) {
            $this->pointeuse->retirer($client->empreinte_id);
        }
        if ($client->photo_path) {
            Storage::disk('public')->delete($client->photo_path);
        }

        $nom = $client->nom_complet;
        $client->forceDelete();
        Log::notice('Client supprimé définitivement', ['client' => $id, 'par' => auth()->id()]);

        return redirect()->route('clients.index')->with('succes', "{$nom} a été supprimé(e) définitivement.");
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
            'date_adhesion' => ['nullable', 'date', 'after:2000-01-01', 'before_or_equal:'.today()->addYear()->toDateString()],
            'sexe' => ['nullable', Rule::in(['M', 'F'])],
            'empreinte_id' => ['nullable', 'string', Empreinte::REGLE_CLIENT, Rule::unique('clients', 'empreinte_id')->ignore($client?->id)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
        ], [
            'empreinte_id.unique' => 'Ce n° de pointeuse est déjà attribué à un autre client.',
            'telephone.unique' => 'Ce numéro de téléphone est déjà enregistré.',
            'telephone.regex' => 'Le numéro de téléphone n\'est pas valide.',
            'empreinte_id.regex' => 'Le n° de pointeuse est un nombre entier (ex. 12), inférieur à 900000000 (plage réservée au personnel).',
        ]);

        unset($data['photo']);

        return $data;
    }
}
