@extends('layouts.app')
@section('title', $client->exists ? 'Modifier le client' : 'Nouveau client')

@php
    use App\Models\Client;
@endphp

@section('content')
<x-page-header :title="$client->exists ? 'Modifier '.$client->nom_complet : 'Nouveau client'"
               subtitle="Les champs marqués * sont obligatoires. Le badge peut être attribué plus tard."/>

<form method="POST" enctype="multipart/form-data"
      action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}"
      class="grid gap-6 lg:grid-cols-3">
    @csrf
    @if($client->exists) @method('PUT') @endif
    @if(request('apres') === 'abonner' || old('apres') === 'abonner')
        <input type="hidden" name="apres" value="abonner">
    @endif

    <div class="card lg:col-span-2">
        <div class="card-header"><h2 class="card-title">Identité</h2></div>
        <div class="card-body grid gap-5 sm:grid-cols-2">
            <div>
                <label for="nom" class="label">Nom *</label>
                <input id="nom" type="text" name="nom" required maxlength="100" value="{{ old('nom', $client->nom) }}" class="input">
            </div>
            <div>
                <label for="prenoms" class="label">Prénoms</label>
                <input id="prenoms" type="text" name="prenoms" maxlength="150" value="{{ old('prenoms', $client->prenoms) }}" class="input">
            </div>
            <div>
                <label for="telephone" class="label">Téléphone</label>
                <input id="telephone" type="tel" name="telephone" maxlength="20" value="{{ old('telephone', $client->telephone) }}" class="input" placeholder="07 00 00 00 00">
            </div>
            <div>
                <label for="email" class="label">E-mail</label>
                <input id="email" type="email" name="email" maxlength="150" value="{{ old('email', $client->email) }}" class="input">
            </div>
            <div>
                <label for="date_naissance" class="label">Date de naissance</label>
                <input id="date_naissance" type="date" name="date_naissance" value="{{ old('date_naissance', $client->date_naissance?->toDateString()) }}" class="input">
            </div>
            <div>
                <label for="sexe" class="label">Sexe</label>
                <select id="sexe" name="sexe" class="input">
                    <option value="">—</option>
                    <option value="M" @selected(old('sexe', $client->sexe) === 'M')>Homme</option>
                    <option value="F" @selected(old('sexe', $client->sexe) === 'F')>Femme</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label for="notes" class="label">Notes</label>
                <textarea id="notes" name="notes" rows="3" maxlength="1000" class="input" placeholder="Objectif, contre-indication médicale, remarque…">{{ old('notes', $client->notes) }}</textarea>
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Accès à la salle</h2></div>
            <div class="card-body space-y-5">
                <fieldset>
                    <legend class="label">Type de client *</legend>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach(Client::TYPES as $valeur => $libelle)
                            <label class="choice items-center text-center text-sm font-semibold">
                                <input type="radio" name="type" value="{{ $valeur }}" required @checked(old('type', $client->type) === $valeur)>
                                {{ $libelle }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div>
                    <label for="badge_id" class="label">N° de badge</label>
                    <input id="badge_id" data-no-enter type="text" name="badge_id" maxlength="64" value="{{ old('badge_id', $client->badge_id) }}" class="input font-mono" placeholder="Passez le badge sur le lecteur USB">
                    <p class="hint">Cliquez dans le champ puis passez le badge sur le lecteur : le numéro s'inscrit tout seul.</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Photo</h2></div>
            <div class="card-body flex items-center gap-4">
                @if($client->exists)
                    <x-avatar :client="$client" size="size-16" text="text-xl"/>
                @endif
                <div class="min-w-0 flex-1">
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-semibold hover:file:bg-slate-200">
                    <p class="hint">JPG, PNG ou WebP · 2 Mo max. Affichée à l'accueil quand le client badge.</p>
                </div>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="btn-primary flex-1"><x-icon name="check" class="size-4"/> Enregistrer</button>
            <a href="{{ $client->exists ? route('clients.show', $client) : route('clients.index') }}" class="btn-light">Annuler</a>
        </div>
    </div>
</form>
@endsection
