@extends('layouts.app')

@section('titre', $service->exists ? 'Modifier un service' : 'Nouveau service')

@section('contenu')
<div class="page-titre">
    <div><h2>@include('partials.icone', ['nom' => 'ciseaux']) {{ $service->exists ? 'Modifier « '.$service->nom.' »' : 'Nouveau service' }}</h2></div>
</div>

<div class="carte" style="max-width:640px">
    <form method="POST" action="{{ $service->exists ? route('services.update', $service) : route('services.store') }}" class="carte-corps">
        @csrf
        @if($service->exists) @method('PUT') @endif

        <div class="champ">
            <label for="nom">Nom du service</label>
            <input type="text" id="nom" name="nom" value="{{ old('nom', $service->nom) }}" required maxlength="100" autofocus>
            @error('nom')<div class="erreur">{{ $message }}</div>@enderror
        </div>

        <div class="grille-2">
            <div class="champ">
                <label for="prix">Prix (FCFA)</label>
                <input type="number" id="prix" name="prix" value="{{ old('prix', $service->prix) }}" min="0" step="50" required>
                @error('prix')<div class="erreur">{{ $message }}</div>@enderror
            </div>
            <div class="champ">
                <label for="code">Code</label>
                <input type="text" id="code" name="code" value="{{ old('code', $service->code) }}" required maxlength="20" placeholder="Ex. COI-007">
                @error('code')<div class="erreur">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="grille-2">
            <div class="champ">
                <label for="categorie_id">Catégorie</label>
                <select id="categorie_id" name="categorie_id" required>
                    @foreach($categories as $c)
                        <option value="{{ $c->id }}" @selected(old('categorie_id', $service->categorie_id) == $c->id)>{{ $c->nom }}</option>
                    @endforeach
                </select>
                @error('categorie_id')<div class="erreur">{{ $message }}</div>@enderror
            </div>
            <div class="champ">
                <label for="ordre">Ordre d'affichage</label>
                <input type="number" id="ordre" name="ordre" value="{{ old('ordre', $service->ordre ?? 0) }}" min="0" max="999">
            </div>
        </div>

        <div class="champ">
            <label class="coche"><input type="checkbox" name="actif" value="1" @checked(old('actif', $service->actif))> Visible en caisse</label>
        </div>

        <div style="display:flex;gap:10px">
            <button class="btn btn-primaire" type="submit">Enregistrer</button>
            <a class="btn" href="{{ route('services.index') }}">Annuler</a>
        </div>
    </form>
</div>
@endsection
