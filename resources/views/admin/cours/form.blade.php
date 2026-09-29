@extends('layouts.app')
@section('title', $cours->exists ? 'Modifier le cours' : 'Nouveau cours')

@php use App\Models\Cours; @endphp

@section('content')
<div class="top"><div><div class="eyebrow"><a href="{{ route('admin.cours.index') }}">← Cours collectifs</a></div><h1>{{ $cours->exists ? $cours->nom : 'Nouveau cours' }}</h1></div></div>

<form method="POST" action="{{ $cours->exists ? route('admin.cours.update', $cours) : route('admin.cours.store') }}" class="card form-card">
    @csrf
    @if($cours->exists) @method('PUT') @endif
    <div class="grid g2">
        <label class="fld" for="nom">Nom du cours *<input id="nom" name="nom" value="{{ old('nom', $cours->nom) }}" required maxlength="100" placeholder="ex. Zumba"></label>
        <label class="fld" for="coach_id">Coach
            <select id="coach_id" name="coach_id"><option value="">À définir</option>@foreach($coachs as $coach)<option value="{{ $coach->id }}" @selected((int) old('coach_id', $cours->coach_id) === $coach->id)>{{ $coach->nom }}</option>@endforeach</select>
        </label>
        <label class="fld" for="jour_semaine">Jour *
            <select id="jour_semaine" name="jour_semaine">@foreach(Cours::JOURS as $n => $j)<option value="{{ $n }}" @selected((int) old('jour_semaine', $cours->jour_semaine) === $n)>{{ $j }}</option>@endforeach</select>
        </label>
        <label class="fld" for="heure">Heure *<input id="heure" type="time" name="heure" value="{{ old('heure', $cours->heureCourte()) }}" required></label>
        <label class="fld" for="duree_minutes">Durée (minutes) *<input id="duree_minutes" type="number" name="duree_minutes" min="15" max="300" step="5" value="{{ old('duree_minutes', $cours->duree_minutes) }}" required></label>
        <label class="fld" for="capacite">Nombre de places *<input id="capacite" type="number" name="capacite" min="1" max="500" value="{{ old('capacite', $cours->capacite) }}" required></label>
        @if($salles->count() > 1)
            <label class="fld" for="salle_id">Salle
                <select id="salle_id" name="salle_id"><option value="">—</option>@foreach($salles as $s)<option value="{{ $s->id }}" @selected((int) old('salle_id', $cours->salle_id) === $s->id)>{{ $s->nom }}</option>@endforeach</select>
            </label>
        @else
            <input type="hidden" name="salle_id" value="{{ $salles->first()?->id }}">
        @endif
    </div>
    <label class="check" for="actif"><input id="actif" type="checkbox" name="actif" value="1" @checked(old('actif', $cours->actif))> Au planning</label>
    <div class="actions"><button class="btn">Enregistrer</button><a href="{{ route('admin.cours.index') }}" class="btn ghost">Annuler</a></div>
</form>
@endsection
