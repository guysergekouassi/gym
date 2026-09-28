<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Passage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
                    ->orWhere('telephone', 'like', "%{$q}%"));
            })
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->value()))
            ->withMax(['passages as dernier_passage_le' => fn ($q) => $q->where('statut', Passage::STATUT_AUTORISE)], 'passe_le')
            ->orderBy('nom')
            ->paginate(25)
            ->withQueryString();

        return view('clients.index', ['clients' => $clients]);
    }

    public function create(): View
    {
        return view('clients.form', ['client' => new Client(['type' => Client::TYPE_ABONNE])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->valider($request);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('clients', 'public');
        }

        $client = Client::create($data);

        return redirect()->route('clients.show', $client)->with('succes', 'Client enregistré.');
    }

    public function show(Client $client): View
    {
        $client->load([
            'abonnements' => fn ($q) => $q->with('formule')->latest('date_debut'),
            'paiements' => fn ($q) => $q->latest()->limit(20),
        ]);

        return view('clients.show', [
            'client' => $client,
            'finDroits' => $client->finDesDroits(),
            'passages' => $client->passages()->latest('passe_le')->limit(30)->get(),
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

        $client->update($data);

        return redirect()->route('clients.show', $client)->with('succes', 'Client mis à jour.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $client->delete();

        return redirect()->route('clients.index')->with('succes', 'Client archivé.');
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
            'notes' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);

        unset($data['photo']);

        return $data;
    }
}
