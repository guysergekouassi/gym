@extends('layouts.app')
@section('title', 'Campagnes')

@section('content')
<div class="top">
    <div><div class="eyebrow">Messages WhatsApp à un groupe de membres : promotion, retour des anciens, information</div><h1>Campagnes</h1></div>
    <a href="{{ route('admin.campagnes.create') }}" class="btn">@include('partials.icone', ['nom' => 'plus'])Nouvelle campagne</a>
</div>

<div class="table-wrap">
    <table>
        <thead><tr><th>Campagne</th><th>Cible</th><th class="r">Destinataires</th><th class="r">Envoyés</th><th class="r">En attente</th><th>Créée</th></tr></thead>
        <tbody>
        @forelse($campagnes as $c)
            <tr>
                <td><span class="name">{{ $c->nom }}</span><div class="meta" style="max-width:420px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $c->contenu }}</div></td>
                <td>{{ \App\Http\Controllers\Admin\CampagneController::SEGMENTS[$c->segment] ?? $c->segment }}</td>
                <td class="r">{{ $c->destinataires }}</td>
                <td class="r">{{ $c->envoyes }}</td>
                <td class="r">@if($c->en_attente)<a href="{{ route('taches.index', ['type' => 'campagne']) }}">{{ $c->en_attente }}</a>@else 0 @endif</td>
                <td class="muted">{{ $c->created_at->format('d/m/Y') }} · {{ $c->user?->name }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">Aucune campagne pour l'instant.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
