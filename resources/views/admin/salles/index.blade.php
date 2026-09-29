@extends('layouts.app')
@section('title', 'Salles')

@section('content')
<div class="top">
    <div><div class="eyebrow">Chaque caisse, lecteur et cours appartient à une salle. Les membres entrent dans toutes les salles.</div><h1>Salles et lecteurs</h1></div>
    <div class="actions">
        <a href="{{ route('admin.lecteurs.index') }}" class="btn ghost">@include('partials.icone', ['nom' => 'empreinte'])Lecteurs</a>
        <a href="{{ route('admin.salles.create') }}" class="btn">@include('partials.icone', ['nom' => 'plus'])Nouvelle salle</a>
    </div>
</div>

<div class="grid g3">
    @forelse($salles as $salle)
        <section class="card">
            <div class="card-h"><h2>{{ $salle->nom }}</h2><span class="tag {{ $salle->actif ? 'ok' : 'off' }}">{{ $salle->actif ? 'Ouverte' : 'Fermée' }}</span></div>
            <div class="meta">{{ $salle->adresse ?: 'Adresse non renseignée' }}{{ $salle->telephone ? ' · '.$salle->telephone : '' }}</div>
            <div class="meta">{{ $salle->caisses_count }} caisse(s) · {{ $salle->lecteurs_count }} lecteur(s)</div>
            <div class="actions">
                <a href="{{ route('dashboard', ['salle' => $salle->id]) }}" class="btn sm">Tableau de bord</a>
                <a href="{{ route('admin.salles.edit', $salle) }}" class="btn ghost sm">Modifier</a>
            </div>
        </section>
    @empty
        <p class="empty">Aucune salle.</p>
    @endforelse
</div>
@endsection
