@extends('layouts.app')
@section('title', $salle->exists ? 'Modifier la salle' : 'Nouvelle salle')

@section('content')
<div class="top"><div><div class="eyebrow"><a href="{{ route('admin.salles.index') }}">← Salles</a></div><h1>{{ $salle->exists ? $salle->nom : 'Nouvelle salle' }}</h1></div></div>

<form method="POST" action="{{ $salle->exists ? route('admin.salles.update', $salle) : route('admin.salles.store') }}" class="card form-card">
    @csrf
    @if($salle->exists) @method('PUT') @endif
    <div class="grid g2">
        <label class="fld" for="nom">Nom *<input id="nom" name="nom" value="{{ old('nom', $salle->nom) }}" required maxlength="100" placeholder="ex. GymFlow Riviera"></label>
        <label class="fld" for="telephone">Téléphone<input id="telephone" name="telephone" value="{{ old('telephone', $salle->telephone) }}"></label>
    </div>
    <label class="fld" for="adresse">Adresse<input id="adresse" name="adresse" value="{{ old('adresse', $salle->adresse) }}" maxlength="200" placeholder="ex. Riviera 3, près du carrefour"></label>
    <label class="check" for="actif"><input id="actif" type="checkbox" name="actif" value="1" @checked(old('actif', $salle->actif))> Salle ouverte</label>
    <div class="actions"><button class="btn">Enregistrer</button><a href="{{ route('admin.salles.index') }}" class="btn ghost">Annuler</a></div>
</form>
@endsection
