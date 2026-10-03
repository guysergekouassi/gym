@extends('layouts.app')
@section('title', 'Clients')

@php
    use App\Models\Client;
    use Illuminate\Support\Carbon;
@endphp

@section('content')
<x-page-header title="Clients" subtitle="{{ $totaux['tous'] }} client(s) enregistrés · {{ $totaux['en_regle'] }} abonnement(s) en règle">
    <a href="{{ route('clients.create') }}" class="btn-primary"><x-icon name="user-plus" class="size-4"/> Nouveau client</a>
</x-page-header>

<form method="GET" class="card mb-6 flex flex-wrap items-end gap-3 p-4">
    <div class="relative min-w-60 flex-1">
        <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-5 -translate-y-1/2 text-slate-400"/>
        <input type="search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Nom, téléphone ou n° d'empreinte" class="input pl-11">
    </div>
    <select name="type" class="input w-auto">
        <option value="">Tous les types</option>
        @foreach(Client::TYPES as $valeur => $libelle)
            <option value="{{ $valeur }}" @selected(request('type') === $valeur)>{{ $libelle }}</option>
        @endforeach
    </select>
    <select name="statut" class="input w-auto">
        <option value="">Tous les statuts</option>
        <option value="en_regle" @selected(request('statut') === 'en_regle')>Abonnement en règle</option>
        <option value="expire" @selected(request('statut') === 'expire')>Abonnement expiré</option>
    </select>
    <button type="submit" class="btn-dark">Filtrer</button>
    @if(request()->hasAny(['q', 'type', 'statut']))
        <a href="{{ route('clients.index') }}" class="btn-light">Réinitialiser</a>
    @endif
</form>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
            <tr><th>Client</th><th>Type</th><th>Empreinte</th><th>Abonnement</th><th>Dernière venue</th><th></th></tr>
            </thead>
            <tbody>
            @forelse($clients as $client)
                @php $fin = $client->fin_droits ? Carbon::parse($client->fin_droits) : null; @endphp
                <tr>
                    <td>
                        <a href="{{ route('clients.show', $client) }}" class="flex items-center gap-3">
                            <x-avatar :client="$client"/>
                            <span>
                                <span class="block font-semibold text-slate-900">{{ $client->nom_complet }}</span>
                                <span class="block text-xs text-slate-500">{{ $client->telephone ?? 'Pas de téléphone' }}</span>
                            </span>
                        </a>
                    </td>
                    <td><span class="{{ $client->type === Client::TYPE_ABONNE ? 'pill-blue' : 'pill-gray' }}">{{ Client::TYPES[$client->type] ?? $client->type }}</span></td>
                    <td class="font-mono text-xs">{{ $client->empreinte_id ?? '—' }}</td>
                    <td>
                        @if($fin && $fin->gte(today()))
                            <span class="pill-green">Jusqu'au {{ $fin->format('d/m/Y') }}</span>
                        @elseif($client->type === Client::TYPE_ABONNE)
                            <span class="pill-red">Expiré</span>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="text-slate-600">{{ $client->dernier_passage_le ? Carbon::parse($client->dernier_passage_le)->diffForHumans() : 'Jamais' }}</td>
                    <td class="text-right"><a href="{{ route('clients.show', $client) }}" class="link text-xs">Ouvrir →</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-12 text-center text-slate-400">Aucun client ne correspond à la recherche.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $clients->links() }}</div>
@endsection
