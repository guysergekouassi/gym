@extends('layouts.app')
@section('title', $caisse->exists ? 'Modifier la caisse' : 'Nouvelle caisse')

@section('content')
<div class="top">
    <div>
        <div class="eyebrow"><a href="{{ route('admin.caisses.index') }}">← Caisses</a></div>
        <h1>{{ $caisse->exists ? $caisse->nom : 'Nouvelle caisse' }}</h1>
    </div>
</div>

<form method="POST" action="{{ $caisse->exists ? route('admin.caisses.update', $caisse) : route('admin.caisses.store') }}" class="card form-card">
    @csrf
    @if($caisse->exists) @method('PUT') @endif

    <div class="grid g2">
        <label class="fld" for="nom">Nom de la caisse *
            <input id="nom" type="text" name="nom" required maxlength="100" value="{{ old('nom', $caisse->nom) }}" placeholder="ex. Caisse accueil">
        </label>
        <label class="fld" for="emplacement">Emplacement
            <input id="emplacement" type="text" name="emplacement" maxlength="150" value="{{ old('emplacement', $caisse->emplacement) }}" placeholder="ex. Rez-de-chaussée, bar, espace femmes">
        </label>
        <label class="fld" for="salle_id">Salle
            <select id="salle_id" name="salle_id">
                <option value="">—</option>
                @foreach($salles as $salle)<option value="{{ $salle->id }}" @selected((int) old('salle_id', $caisse->salle_id ?? $salles->first()?->id) === $salle->id)>{{ $salle->nom }}</option>@endforeach
            </select>
        </label>
    </div>

    <label class="check" for="actif">
        <input id="actif" type="checkbox" name="actif" value="1" @checked(old('actif', $caisse->actif))>
        Caisse active
    </label>
    <p class="empty">Une caisse désactivée garde son historique, mais les caissières qui y sont rattachées ne peuvent plus encaisser.</p>

    <div class="actions">
        <button class="btn">{{ $caisse->exists ? 'Enregistrer' : 'Créer la caisse' }}</button>
        <a href="{{ route('admin.caisses.index') }}" class="btn ghost">Annuler</a>
    </div>
</form>
@endsection
