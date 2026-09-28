@extends('layouts.app')
@section('title', 'Clients')

@php
    use App\Models\Client;
    use Illuminate\Support\Carbon;
@endphp

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <h1 class="text-2xl font-bold">Clients</h1>
    <a href="{{ route('clients.create') }}" class="rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 text-sm font-semibold">+ Nouveau client</a>
</div>

<form method="GET" class="flex flex-wrap gap-2 mb-4 text-sm">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Nom ou téléphone" class="border rounded-lg px-3 py-2 w-64">
    <select name="type" class="border rounded-lg px-3 py-2">
        <option value="">Tous les types</option>
        @foreach(Client::TYPES as $valeur => $libelle)
            <option value="{{ $valeur }}" @selected(request('type') === $valeur)>{{ $libelle }}</option>
        @endforeach
    </select>
    <button class="rounded-lg bg-slate-800 text-white px-4 py-2">Rechercher</button>
</form>

<div class="bg-white rounded-xl shadow-sm overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="text-left text-slate-500 bg-slate-50">
        <tr><th class="px-4 py-2">Nom</th><th>Type</th><th>Téléphone</th><th>Empreinte</th><th>Dernière venue</th></tr>
        </thead>
        <tbody>
        @forelse($clients as $client)
            <tr class="border-t hover:bg-slate-50">
                <td class="px-4 py-2"><a href="{{ route('clients.show', $client) }}" class="text-sky-700 hover:underline font-medium">{{ $client->nom_complet }}</a></td>
                <td>{{ Client::TYPES[$client->type] ?? $client->type }}</td>
                <td>{{ $client->telephone ?? '—' }}</td>
                <td>{{ $client->empreinte_id ? '✓ #'.$client->empreinte_id : '—' }}</td>
                <td>{{ $client->dernier_passage_le ? Carbon::parse($client->dernier_passage_le)->diffForHumans() : 'Jamais' }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-4 text-slate-400">Aucun client</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $clients->links() }}</div>
@endsection
