<?php

namespace App\Http\Controllers;

use App\Models\Abonnement;
use App\Models\Client;
use App\Models\Coach;
use App\Models\Gel;
use App\Models\Message;
use App\Models\PackCoaching;
use App\Models\Passage;
use App\Models\SeanceCoaching;
use App\Services\GelService;
use App\Services\MessageService;
use App\Support\Journal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $clients = Client::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = $request->string('q')->trim()->value();
                $query->where(fn ($w) => $w->where('nom', 'like', "%{$q}%")
                    ->orWhere('prenoms', 'like', "%{$q}%")
                    ->orWhere('telephone', 'like', "%{$q}%")
                    ->orWhere('empreinte_id', $q)
                    ->orWhere('carte_id', $q));
            })
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->value()))
            ->withMax(['passages as dernier_passage_le' => fn ($q) => $q->where('statut', Passage::STATUT_AUTORISE)], 'passe_le')
            ->withMax(['abonnements as fin_droits' => fn ($q) => $q
                ->where('statut', Abonnement::STATUT_ACTIF)
                ->whereNull('entrees_restantes')
                ->whereDate('date_fin', '>=', today()->toDateString())], 'date_fin')
            ->orderBy('nom')
            ->paginate(25)
            ->withQueryString();

        return view('clients.index', ['clients' => $clients]);
    }

    public function create(Request $request): View
    {
        // Pré-remplissage depuis « Enrôler » (empreinte ou carte inconnue à l'entrée) ou un prospect
        return view('clients.form', ['client' => new Client([
            'type' => Client::TYPE_ABONNE,
            'empreinte_id' => $request->string('empreinte_id')->limit(64, '')->value() ?: null,
            'carte_id' => $request->string('carte_id')->limit(64, '')->value() ?: null,
            'nom' => $request->string('nom')->limit(100, '')->value() ?: null,
            'telephone' => $request->string('telephone')->limit(20, '')->value() ?: null,
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->valider($request);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('clients', 'public');
        }

        $client = Client::create($data);
        Journal::noter('client.cree', "Fiche de {$client->nom_complet} créée", $client);

        return redirect()->route('clients.show', $client)->with('succes', 'Client enregistré.');
    }

    public function show(Client $client): View
    {
        $client->load([
            'abonnements' => fn ($q) => $q->with('formule')->latest('date_debut'),
            'paiements' => fn ($q) => $q->with(['caisse:id,nom', 'abonnement.formule', 'lignes', 'pack.formule'])->latest()->limit(30),
            'gels' => fn ($q) => $q->latest('du'),
            'mesures' => fn ($q) => $q->orderBy('date'),
            'packs' => fn ($q) => $q->with(['coach', 'formule'])->latest(),
            'parrain', 'filleuls',
        ]);

        $passages = $client->passages()->latest('passe_le')->limit(40)->get();
        $messages = $client->messages()->latest()->limit(20)->get();
        $finDroits = $client->finDesDroits();

        // Assiduité : jours de venue sur les 14 derniers jours
        $venues = $client->passages()->where('statut', Passage::STATUT_AUTORISE)
            ->where('passe_le', '>=', today()->subDays(13))->pluck('passe_le')
            ->map->toDateString()->unique()->flip();
        $jours14 = collect(range(13, 0))->map(fn ($i) => today()->subDays($i))
            ->map(fn ($d) => ['date' => $d, 'venu' => $venues->has($d->toDateString())]);

        $habitude = $client->passages()->where('statut', Passage::STATUT_AUTORISE)
            ->where('passe_le', '>=', today()->subDays(60))->pluck('passe_le')
            ->countBy(fn ($d) => (int) $d->format('G'))->sortDesc()->keys()->first();

        return view('clients.show', [
            'client' => $client,
            'finDroits' => $finDroits,
            'abonnementActif' => $client->abonnementActif(),
            'gelEnCours' => $client->gelEnCours(),
            'passages' => $passages,
            'jours14' => $jours14,
            'habitude' => $habitude,
            'depense' => $client->paiements()->valides()->sum('montant'),
            'risque' => $this->risque($client, $finDroits, $jours14->where('venu', true)->count()),
            'timeline' => $this->timeline($client, $passages, $messages),
            'coachs' => Coach::where('actif', true)->orderBy('nom')->get(),
        ]);
    }

    public function edit(Client $client): View
    {
        return view('clients.form', ['client' => $client]);
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $data = $this->valider($request, $client);

        if ($request->hasFile('photo')) {
            if ($client->photo_path) {
                Storage::disk('public')->delete($client->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('clients', 'public');
        }

        $avant = $client->only(array_keys($data));
        $client->update($data);
        Journal::noter('client.modifie', "Fiche de {$client->nom_complet} modifiée", $client, [
            'champs' => array_keys(array_diff_assoc(array_map('strval', array_filter($data, 'is_scalar')), array_map('strval', array_filter($avant, 'is_scalar')))),
        ]);

        return redirect()->route('clients.show', $client)->with('succes', 'Client mis à jour.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $client->delete();
        Journal::noter('client.archive', "{$client->nom_complet} archivé", $client);

        return redirect()->route('clients.index')->with('succes', 'Client archivé.');
    }

    public function geler(Request $request, Client $client, GelService $gels): RedirectResponse
    {
        $data = $request->validate([
            'du' => ['required', 'date', 'after_or_equal:today'],
            'jours' => ['required', 'integer', 'min:1', 'max:180'],
            'motif' => ['nullable', 'string', 'max:255'],
        ]);

        $gel = $gels->geler($client, $request->date('du'), (int) $data['jours'], $data['motif'] ?? null);

        return back()->with('succes', "Abonnement gelé du {$gel->du->format('d/m/Y')} au {$gel->au->format('d/m/Y')} : {$gel->jours} jour(s) ajoutés à la fin.");
    }

    public function terminerGel(Gel $gel, GelService $gels): RedirectResponse
    {
        $gels->terminer($gel);

        return back()->with('succes', 'Gel terminé : le membre peut de nouveau entrer.');
    }

    public function mesure(Request $request, Client $client): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'poids' => ['nullable', 'numeric', 'min:20', 'max:350'],
            'taille' => ['nullable', 'integer', 'min:100', 'max:250'],
            'tour_taille' => ['nullable', 'numeric', 'min:30', 'max:250'],
            'masse_grasse' => ['nullable', 'numeric', 'min:2', 'max:70'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        if (! array_filter(array_intersect_key($data, array_flip(['poids', 'taille', 'tour_taille', 'masse_grasse'])))) {
            throw ValidationException::withMessages(['poids' => 'Saisissez au moins une mesure.']);
        }

        $client->mesures()->create($data + ['user_id' => $request->user()->id]);

        return back()->with('succes', 'Mesure enregistrée.');
    }

    public function suivi(Request $request, Client $client): RedirectResponse
    {
        $client->update($request->validate([
            'objectif' => ['nullable', 'string', 'max:255'],
            'programme' => ['nullable', 'string', 'max:5000'],
        ]));

        return back()->with('succes', 'Objectif et programme enregistrés.');
    }

    /** Génère un nouveau lien personnel vers l'espace membre et prépare le message WhatsApp. */
    public function lienMembre(Client $client, MessageService $messages): RedirectResponse
    {
        $lien = route('membre.lien', $client->genererJetonMembre());
        Journal::noter('membre.lien', "Lien espace membre généré pour {$client->nom_complet}", $client);

        $message = new Message([
            'telephone' => $client->telephone,
            'contenu' => $messages->rediger(config('salle.messagerie.modeles.lien_membre'), ['prenom' => $client->appel, 'lien' => $lien]),
        ]);

        return back()->with('lien_membre', ['url' => $lien, 'whatsapp' => $message->lienWhatsapp()]);
    }

    public function seanceCoaching(Request $request, PackCoaching $pack): RedirectResponse
    {
        $data = $request->validate(['coach_id' => ['nullable', 'integer', 'exists:coachs,id']]);

        DB::transaction(function () use ($pack, $data, $request) {
            $pack = PackCoaching::lockForUpdate()->findOrFail($pack->id);
            if ($pack->statut !== 'actif' || $pack->seances_restantes < 1) {
                throw ValidationException::withMessages(['pack' => 'Ce pack n’a plus de séances.']);
            }

            SeanceCoaching::create([
                'pack_id' => $pack->id,
                'coach_id' => $data['coach_id'] ?? $pack->coach_id,
                'faite_le' => now(),
                'user_id' => $request->user()->id,
            ]);

            $pack->decrement('seances_restantes');
            if ($pack->seances_restantes === 0) {
                $pack->update(['statut' => 'termine']);
            }
        });

        return back()->with('succes', 'Séance de coaching enregistrée.');
    }

    /**
     * Enrôlement : dernier doigt ou dernière carte non reconnu(e) scanné(e) depuis l'ouverture de la fenêtre de capture.
     * Le lecteur envoie le numéro qu'il a attribué ; il suffit de le reporter dans la fiche.
     */
    public function capture(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['empreinte', 'carte'])],
            'depuis' => ['required', 'integer'],
        ]);

        $passage = Passage::where('motif', $data['type'] === 'carte' ? 'carte_inconnue' : 'empreinte_inconnue')
            ->where('passe_le', '>=', \Illuminate\Support\Carbon::createFromTimestamp($data['depuis'])->subSeconds(2))
            ->latest('passe_le')
            ->latest('id')
            ->first();

        $colonne = $data['type'] === 'carte' ? 'carte_id' : 'empreinte_id';
        $dejaPris = $passage && Client::withTrashed()->where($colonne, $passage->empreinte_id)->exists();

        return response()->json([
            'identifiant' => $passage && ! $dejaPris ? $passage->empreinte_id : null,
            'deja_attribue' => $dejaPris,
        ]);
    }

    /** Estimation simple du risque de départ, pour prioriser les relances. */
    private function risque(Client $client, $finDroits, int $venues14): array
    {
        if ($client->type !== Client::TYPE_ABONNE) {
            return ['niveau' => '—', 'classe' => 'info', 'raison' => 'Client journalier'];
        }
        if (! $finDroits && ! $client->abonnementActif()) {
            return ['niveau' => 'Parti', 'classe' => 'ko', 'raison' => 'Plus d’abonnement en cours'];
        }
        if ($venues14 === 0) {
            return ['niveau' => 'Élevé', 'classe' => 'ko', 'raison' => 'Aucune venue depuis 14 jours'];
        }
        if ($venues14 <= 2 || ($finDroits && today()->diffInDays($finDroits, false) <= 7)) {
            return ['niveau' => 'Moyen', 'classe' => 'warn', 'raison' => $venues14 <= 2 ? 'Vient rarement' : 'Échéance proche'];
        }

        return ['niveau' => 'Faible', 'classe' => 'ok', 'raison' => 'Vient régulièrement'];
    }

    /** Frise unique : entrées, paiements, messages, gels, mesures. */
    private function timeline(Client $client, $passages, $messages)
    {
        $ev = collect();

        foreach ($passages as $p) {
            $ev->push(['date' => $p->passe_le, 'classe' => $p->estAutorise() ? 'in' : 'ko',
                'titre' => $p->estAutorise() ? 'Entrée · '.(\App\Models\Passage::METHODES[$p->methode] ?? $p->methode) : 'Refusé · '.$p->message(), 'detail' => null]);
        }
        foreach ($client->paiements as $pa) {
            $ev->push(['date' => $pa->created_at, 'classe' => $pa->estAnnule() ? 'ko' : 'pay',
                'titre' => ($pa->estAnnule() ? 'Annulé · ' : '').$pa->objet().' · '.number_format($pa->montant, 0, ',', ' ').' F',
                'detail' => ($pa->caisse?->nom ?? '').' · '.(\App\Models\Paiement::MODES[$pa->mode] ?? $pa->mode), 'recu' => $pa]);
        }
        foreach ($messages as $m) {
            $ev->push(['date' => $m->envoye_le ?? $m->created_at, 'classe' => 'msg',
                'titre' => 'Message '.(Message::TYPES[$m->type] ?? $m->type).' · '.['a_envoyer' => 'à envoyer', 'envoye' => 'envoyé', 'ignore' => 'ignoré', 'echec' => 'échec'][$m->statut], 'detail' => null]);
        }
        foreach ($client->gels as $g) {
            $ev->push(['date' => $g->created_at, 'classe' => 'gel', 'titre' => "Gel {$g->du->format('d/m')} → {$g->au->format('d/m')} ({$g->jours} j)", 'detail' => $g->motif]);
        }

        return $ev->sortByDesc('date')->take(25)->values();
    }

    private function valider(Request $request, ?Client $client = null): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Client::TYPES))],
            'nom' => ['required', 'string', 'max:100'],
            'prenoms' => ['nullable', 'string', 'max:150'],
            'telephone' => ['nullable', 'string', 'max:20', Rule::unique('clients', 'telephone')->ignore($client?->id)],
            'email' => ['nullable', 'email', 'max:150'],
            'date_naissance' => ['nullable', 'date', 'before:today'],
            'sexe' => ['nullable', Rule::in(['M', 'F'])],
            'empreinte_id' => ['nullable', 'string', 'max:64', Rule::unique('clients', 'empreinte_id')->ignore($client?->id)],
            'carte_id' => ['nullable', 'string', 'max:64', Rule::unique('clients', 'carte_id')->ignore($client?->id)],
            'parrain_telephone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);

        if (! empty($data['parrain_telephone'])) {
            $parrain = Client::where('telephone', $data['parrain_telephone'])->where('id', '!=', $client?->id)->first();
            if (! $parrain) {
                throw ValidationException::withMessages(['parrain_telephone' => 'Aucun client avec ce numéro : vérifiez le téléphone du parrain.']);
            }
            $data['parrain_id'] = $parrain->id;
        } elseif ($request->has('parrain_telephone')) {
            $data['parrain_id'] = null;
        }

        unset($data['photo'], $data['parrain_telephone']);

        return $data;
    }
}
