@extends('layouts.app')
@section('title', 'Bar et boutique')

@php use App\Support\Fcfa; @endphp

@section('content')
<div class="top">
    <div><div class="eyebrow">Articles vendus au comptoir depuis l'onglet « Bar et boutique » de la caisse</div><h1>Bar et boutique</h1></div>
    <a href="{{ route('admin.produits.create') }}" class="btn">@include('partials.icone', ['nom' => 'plus'])Nouvel article</a>
</div>

<div class="table-wrap">
    <table>
        <thead><tr><th>Article</th><th class="r">Prix</th><th class="r">Stock</th><th>Approvisionner ou corriger</th><th>Statut</th><th><span class="sr">Actions</span></th></tr></thead>
        <tbody>
        @forelse($produits as $produit)
            <tr>
                <td class="name">{{ $produit->nom }}</td>
                <td class="r">{{ Fcfa::format($produit->prix) }}</td>
                <td class="r"><span class="tag {{ $produit->stock <= $produit->seuil_alerte ? ($produit->stock <= 0 ? 'ko' : 'warn') : 'ok' }}">{{ $produit->stock }}</span></td>
                <td>
                    <form method="POST" action="{{ route('admin.produits.stock', $produit) }}" class="inline-form" style="flex-wrap:nowrap">
                        @csrf
                        <label class="sr" for="q-{{ $produit->id }}">Quantité</label>
                        <input id="q-{{ $produit->id }}" type="number" name="quantite" placeholder="+24 ou −2" required class="btn ghost sm" style="width:110px;text-align:right">
                        <label class="sr" for="m-{{ $produit->id }}">Motif</label>
                        <select id="m-{{ $produit->id }}" name="motif" class="btn ghost sm"><option>approvisionnement</option><option>casse ou périmé</option><option>correction d'inventaire</option><option>consommation interne</option></select>
                        <button class="pill-btn">Valider</button>
                    </form>
                </td>
                <td><span class="tag {{ $produit->actif ? 'ok' : 'off' }}">{{ $produit->actif ? 'En vente' : 'Retiré' }}</span></td>
                <td class="r"><a href="{{ route('admin.produits.edit', $produit) }}" class="pill-btn">Modifier</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">Aucun article. Exemple : eau minérale 50 cl à 300 F, stock 48.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<section class="card">
    <h2>Derniers mouvements de stock</h2>
    <div class="rows">
        @forelse($mouvements as $m)
            <div class="row">
                <span class="time" style="width:84px">{{ $m->created_at->format('d/m H:i') }}</span>
                <div class="grow"><span class="name">{{ $m->produit->nom }}</span><div class="meta">{{ $m->motif }} · {{ $m->user?->name ?? '—' }}</div></div>
                <span class="num" style="font-size:18px;color:{{ $m->quantite > 0 ? 'var(--accent-ink)' : 'var(--danger)' }}">{{ $m->quantite > 0 ? '+' : '' }}{{ $m->quantite }}</span>
            </div>
        @empty
            <p class="empty">Aucun mouvement.</p>
        @endforelse
    </div>
</section>
@endsection
