@extends('layouts.app')
@section('title', 'Clients')

@php
    use App\Models\Client;
    use Illuminate\Support\Carbon;
@endphp

@section('content')
<div class="top">
    <div>
        <div class="eyebrow">{{ $clients->total() }} fiche(s)</div>
        <h1>Clients</h1>
    </div>
    <a href="{{ route('clients.create') }}" class="btn">@include('partials.icone', ['nom' => 'plus'])Nouveau client</a>
</div>

<form method="GET" class="filters">
    <label class="fld" for="q">Rechercher<input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Nom, téléphone ou n° d'empreinte"></label>
    <label class="fld" for="type" style="flex:0 1 200px">Type
        <select id="type" name="type">
            <option value="">Tous les types</option>
            @foreach(Client::TYPES as $valeur => $libelle)
                <option value="{{ $valeur }}" @selected(request('type') === $valeur)>{{ $libelle }}</option>
            @endforeach
        </select>
    </label>
    <button class="btn ghost">Rechercher</button>
</form>

<div class="table-wrap">
    <table>
        <thead><tr><th>Client</th><th>Type</th><th>Téléphone</th><th>Droits</th><th>Empreinte</th><th>Dernière venue</th><th><span class="sr">Actions</span></th></tr></thead>
        <tbody>
        @forelse($clients as $client)
            @php($fin = $client->fin_droits ? Carbon::parse($client->fin_droits) : null)
            <tr>
                <td>
                    <div class="who">
                        <div class="av">@if($client->photo_url)<img src="{{ $client->photo_url }}" alt="">@else{{ mb_strtoupper(mb_substr($client->nom, 0, 1).mb_substr($client->prenoms ?? '', 0, 1)) }}@endif</div>
                        <a class="name" href="{{ route('clients.show', $client) }}">{{ $client->nom_complet }}</a>
                    </div>
                </td>
                <td>{{ Client::TYPES[$client->type] ?? $client->type }}</td>
                <td style="white-space:nowrap">{{ $client->telephone ?? '—' }}</td>
                <td>
                    @if($fin)
                        @php($reste = (int) today()->diffInDays($fin, false))
                        <span class="tag {{ $reste <= (int) config('salle.kpi.expiration_alerte_jours') ? 'warn' : 'ok' }}">Jusqu’au {{ $fin->format('d/m/Y') }}</span>
                    @elseif($client->type === Client::TYPE_ABONNE)
                        <span class="tag ko">Expiré</span>
                    @else
                        <span class="tag info">Journalier</span>
                    @endif
                </td>
                <td>{{ $client->empreinte_id ? 'n° '.$client->empreinte_id : '' }}@unless($client->empreinte_id)<span class="muted">Non enrôlé</span>@endunless</td>
                <td class="muted" style="white-space:nowrap">{{ $client->dernier_passage_le ? Carbon::parse($client->dernier_passage_le)->diffForHumans() : 'Jamais' }}</td>
                <td class="r">
                    @if(auth()->user()->isCaissier())
                        <a class="pill-btn" href="{{ route('caisse.index', ['client_id' => $client->id]) }}#abonnement">{{ $fin ? 'Prolonger' : ($client->type === Client::TYPE_ABONNE ? 'Renouveler' : 'Abonner') }}</a>
                    @else
                        <a class="pill-btn" href="{{ route('clients.show', $client) }}">Fiche</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">Aucun client{{ request()->hasAny(['q', 'type']) ? ' ne correspond à cette recherche' : '' }}.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="pager">{{ $clients->links('partials.pagination') }}</div>
@endsection
