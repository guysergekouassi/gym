@extends('layouts.app')
@section('title', 'Caissières')

@php
    use Illuminate\Support\Carbon;
@endphp

@section('content')
<div class="top">
    <div>
        <div class="eyebrow">Comptes de l'espace caisse et caisse attribuée à chacune</div>
        <h1>Caissières</h1>
    </div>
    <a href="{{ route('admin.caissiers.create') }}" class="btn">@include('partials.icone', ['nom' => 'plus'])Nouvelle caissière</a>
</div>

<div class="table-wrap">
    <table>
        <thead><tr><th>Nom</th><th>E-mail de connexion</th><th>Caisse</th><th>Statut</th><th>Dernier encaissement</th><th><span class="sr">Actions</span></th></tr></thead>
        <tbody>
        @forelse($caissiers as $caissier)
            <tr>
                <td><div class="who"><div class="av">{{ mb_strtoupper(mb_substr($caissier->name, 0, 2)) }}</div><span class="name">{{ $caissier->name }}</span></div></td>
                <td>{{ $caissier->email }}</td>
                <td>
                    @if($caissier->caisse)
                        {{ $caissier->caisse->nom }}
                        @unless($caissier->caisse->actif)<div class="meta ko">Caisse désactivée</div>@endunless
                    @else
                        <span class="tag warn">Aucune caisse</span>
                    @endif
                </td>
                <td><span class="tag {{ $caissier->actif ? 'ok' : 'off' }}">{{ $caissier->actif ? 'Actif' : 'Désactivé' }}</span></td>
                <td class="muted">{{ $caissier->dernier_encaissement_le ? Carbon::parse($caissier->dernier_encaissement_le)->diffForHumans() : 'Jamais' }}</td>
                <td class="r"><a href="{{ route('admin.caissiers.edit', $caissier) }}" class="pill-btn">Modifier</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">Aucune caissière. Créez un compte pour chaque personne qui encaisse.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
