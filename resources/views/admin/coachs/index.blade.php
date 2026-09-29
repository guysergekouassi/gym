@extends('layouts.app')
@section('title', 'Coachs')

@section('content')
<div class="top">
    <div><div class="eyebrow">Coachs, cours collectifs et coaching personnel</div><h1>Coachs et cours</h1></div>
    <div class="actions">
        <a href="{{ route('admin.cours.index') }}" class="btn ghost">@include('partials.icone', ['nom' => 'calendrier'])Cours collectifs</a>
        <a href="{{ route('admin.coachs.commissions') }}" class="btn ghost">Commissions du mois</a>
        <a href="{{ route('admin.coachs.create') }}" class="btn">@include('partials.icone', ['nom' => 'plus'])Nouveau coach</a>
    </div>
</div>

<div class="table-wrap">
    <table>
        <thead><tr><th>Coach</th><th>Spécialité</th><th>Téléphone</th><th class="r">Commission</th><th class="r">Cours</th><th class="r">Packs actifs</th><th>Statut</th><th><span class="sr">Actions</span></th></tr></thead>
        <tbody>
        @forelse($coachs as $coach)
            <tr>
                <td><div class="who"><div class="av">{{ mb_strtoupper(mb_substr($coach->nom, 0, 2)) }}</div><span class="name">{{ $coach->nom }}</span></div></td>
                <td>{{ $coach->specialite ?? '—' }}</td>
                <td>{{ $coach->telephone ?? '—' }}</td>
                <td class="r">{{ $coach->commission_pct }} %</td>
                <td class="r">{{ $coach->cours_count }}</td>
                <td class="r">{{ $coach->packs_actifs }}</td>
                <td><span class="tag {{ $coach->actif ? 'ok' : 'off' }}">{{ $coach->actif ? 'Actif' : 'Inactif' }}</span></td>
                <td class="r"><a href="{{ route('admin.coachs.edit', $coach) }}" class="pill-btn">Modifier</a></td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted">Aucun coach. Ajoutez vos coachs pour leur attribuer des cours et des packs de coaching personnel.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
