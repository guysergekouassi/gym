@extends('layouts.app')
@section('title', 'Formules')

@php
    use App\Models\Formule;
    use App\Support\Fcfa;
@endphp

@section('content')
<div class="top">
    <div><div class="eyebrow">Ce que la salle vend : abonnements, carnets d'entrées, packs de coaching</div><h1>Formules et promos</h1></div>
    <div class="actions">
        <a href="{{ route('admin.promos.index') }}" class="btn ghost">@include('partials.icone', ['nom' => 'etiquette'])Codes promo</a>
        <a href="{{ route('admin.formules.create') }}" class="btn">@include('partials.icone', ['nom' => 'plus'])Nouvelle formule</a>
    </div>
</div>

@if((int) config('salle.frais_inscription') > 0)
    <p class="hint-card" style="margin:0">Frais d'inscription ajoutés au premier abonnement : <strong>{{ Fcfa::format((int) config('salle.frais_inscription')) }}</strong> (réglage <code>SALLE_FRAIS_INSCRIPTION</code> dans le fichier .env).</p>
@else
    <p class="hint-card" style="margin:0">Aucun frais d'inscription. Pour en ajouter, renseignez <code>SALLE_FRAIS_INSCRIPTION=5000</code> dans le fichier .env.</p>
@endif

@foreach(Formule::TYPES as $type => $libelle)
    <section class="grid" style="gap:10px">
        <h2 style="font-size:18px">{{ $libelle }}</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Formule</th><th>Catégorie</th><th>Contenu</th><th class="r">Prix</th><th class="r">En cours</th><th>Statut</th><th><span class="sr">Actions</span></th></tr></thead>
                <tbody>
                @forelse($formules->get($type, collect()) as $formule)
                    <tr>
                        <td><span class="name">{{ $formule->nom }}</span>@if($formule->description)<div class="meta">{{ $formule->description }}</div>@endif</td>
                        <td>{{ $formule->categorie ?? '—' }}</td>
                        <td>{{ $formule->resume() }}</td>
                        <td class="r"><span class="num" style="font-size:18px">{{ Fcfa::format($formule->prix) }}</span></td>
                        <td class="r">{{ $type === Formule::TYPE_COACHING ? '—' : $formule->en_cours }}</td>
                        <td><span class="tag {{ $formule->actif ? 'ok' : 'off' }}">{{ $formule->actif ? 'En vente' : 'Retirée' }}</span></td>
                        <td class="r"><a href="{{ route('admin.formules.edit', $formule) }}" class="pill-btn">Modifier</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Aucune formule de ce type.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endforeach
@endsection
