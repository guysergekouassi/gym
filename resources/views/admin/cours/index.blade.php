@extends('layouts.app')
@section('title', 'Cours collectifs')

@php use App\Models\Cours; @endphp

@section('content')
<div class="top">
    <div><div class="eyebrow"><a href="{{ route('admin.coachs.index') }}">← Coachs</a> · chaque cours se répète toutes les semaines</div><h1>Cours collectifs</h1></div>
    <div class="actions">
        <a href="{{ route('planning.index') }}" class="btn ghost">Voir le planning</a>
        <a href="{{ route('admin.cours.create') }}" class="btn">@include('partials.icone', ['nom' => 'plus'])Nouveau cours</a>
    </div>
</div>

<div class="table-wrap">
    <table>
        <thead><tr><th>Cours</th><th>Jour</th><th>Heure</th><th>Coach</th><th>Salle</th><th class="r">Places</th><th>Statut</th><th><span class="sr">Actions</span></th></tr></thead>
        <tbody>
        @forelse($cours as $c)
            <tr>
                <td class="name">{{ $c->nom }}</td>
                <td>{{ Cours::JOURS[$c->jour_semaine] }}</td>
                <td class="time">{{ $c->heureCourte() }} · {{ $c->duree_minutes }} min</td>
                <td>{{ $c->coach?->nom ?? '—' }}</td>
                <td>{{ $c->salle?->nom ?? '—' }}</td>
                <td class="r">{{ $c->capacite }}</td>
                <td><span class="tag {{ $c->actif ? 'ok' : 'off' }}">{{ $c->actif ? 'Au planning' : 'Retiré' }}</span></td>
                <td class="r"><a href="{{ route('admin.cours.edit', $c) }}" class="pill-btn">Modifier</a></td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted">Aucun cours. Exemple : Zumba, le lundi à 18 h 30, 25 places.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
