@extends('layouts.app')
@section('title', $produit->exists ? 'Modifier l’article' : 'Nouvel article')

@section('content')
<div class="top"><div><div class="eyebrow"><a href="{{ route('admin.produits.index') }}">← Bar et boutique</a></div><h1>{{ $produit->exists ? $produit->nom : 'Nouvel article' }}</h1></div></div>

<form method="POST" action="{{ $produit->exists ? route('admin.produits.update', $produit) : route('admin.produits.store') }}" class="card form-card">
    @csrf
    @if($produit->exists) @method('PUT') @endif
    <div class="grid g2">
        <label class="fld" for="nom">Nom *<input id="nom" name="nom" value="{{ old('nom', $produit->nom) }}" required maxlength="100" placeholder="ex. Eau minérale 50 cl"></label>
        <label class="fld" for="prix">Prix de vente (FCFA) *<input id="prix" type="number" name="prix" min="0" step="50" value="{{ old('prix', $produit->prix) }}" required></label>
        @unless($produit->exists)
            <label class="fld" for="stock">Stock de départ *<input id="stock" type="number" name="stock" min="0" value="{{ old('stock', 0) }}" required></label>
        @endunless
        <label class="fld" for="seuil_alerte">Alerte quand il en reste<input id="seuil_alerte" type="number" name="seuil_alerte" min="0" value="{{ old('seuil_alerte', $produit->seuil_alerte) }}" required><span class="aide">Le tableau de bord signale l'article à réapprovisionner.</span></label>
    </div>
    <label class="check" for="actif"><input id="actif" type="checkbox" name="actif" value="1" @checked(old('actif', $produit->actif))> En vente à la caisse</label>
    <div class="actions"><button class="btn">Enregistrer</button><a href="{{ route('admin.produits.index') }}" class="btn ghost">Annuler</a></div>
</form>
@endsection
