@extends('layouts.app')
@section('title', $client->exists ? 'Modifier le client' : 'Nouveau client')

@php
    use App\Models\Client;
@endphp

@section('content')
<h1 class="text-2xl font-bold mb-6">{{ $client->exists ? 'Modifier '.$client->nom_complet : 'Nouveau client' }}</h1>

<form method="POST" enctype="multipart/form-data"
      action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}"
      class="bg-white rounded-xl p-6 shadow-sm grid md:grid-cols-2 gap-4 max-w-3xl">
    @csrf
    @if($client->exists) @method('PUT') @endif

    <label class="text-sm">Type
        <select name="type" required class="mt-1 w-full border rounded-lg px-3 py-2">
            @foreach(Client::TYPES as $valeur => $libelle)
                <option value="{{ $valeur }}" @selected(old('type', $client->type) === $valeur)>{{ $libelle }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-sm">Sexe
        <select name="sexe" class="mt-1 w-full border rounded-lg px-3 py-2">
            <option value="">—</option>
            <option value="M" @selected(old('sexe', $client->sexe) === 'M')>Homme</option>
            <option value="F" @selected(old('sexe', $client->sexe) === 'F')>Femme</option>
        </select>
    </label>

    <label class="text-sm">Nom *
        <input type="text" name="nom" required value="{{ old('nom', $client->nom) }}" class="mt-1 w-full border rounded-lg px-3 py-2">
    </label>
    <label class="text-sm">Prénoms
        <input type="text" name="prenoms" value="{{ old('prenoms', $client->prenoms) }}" class="mt-1 w-full border rounded-lg px-3 py-2">
    </label>

    <label class="text-sm">Téléphone
        <input type="tel" name="telephone" value="{{ old('telephone', $client->telephone) }}" class="mt-1 w-full border rounded-lg px-3 py-2">
    </label>
    <label class="text-sm">E-mail
        <input type="email" name="email" value="{{ old('email', $client->email) }}" class="mt-1 w-full border rounded-lg px-3 py-2">
    </label>

    <label class="text-sm">Date de naissance
        <input type="date" name="date_naissance" value="{{ old('date_naissance', $client->date_naissance?->toDateString()) }}" class="mt-1 w-full border rounded-lg px-3 py-2">
    </label>
    <label class="text-sm">ID empreinte (utilisateur enrôlé sur le lecteur)
        <input type="text" name="empreinte_id" value="{{ old('empreinte_id', $client->empreinte_id) }}" placeholder="ex. 125" class="mt-1 w-full border rounded-lg px-3 py-2">
        <span class="text-xs text-slate-500">Enrôle d'abord le doigt sur l'appareil, puis reporte ici le numéro attribué.</span>
    </label>

    <label class="text-sm md:col-span-2">Photo
        <input type="file" name="photo" accept="image/*" class="mt-1 block w-full text-sm">
        @if($client->photo_url)
            <img src="{{ $client->photo_url }}" alt="Photo actuelle" class="mt-2 h-20 w-20 object-cover rounded-lg">
        @endif
    </label>

    <label class="text-sm md:col-span-2">Notes
        <textarea name="notes" rows="3" class="mt-1 w-full border rounded-lg px-3 py-2">{{ old('notes', $client->notes) }}</textarea>
    </label>

    <div class="md:col-span-2 flex gap-3">
        <button class="rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2 font-semibold">Enregistrer</button>
        <a href="{{ $client->exists ? route('clients.show', $client) : route('clients.index') }}" class="rounded-lg bg-slate-200 px-5 py-2">Annuler</a>
    </div>
</form>
@endsection
