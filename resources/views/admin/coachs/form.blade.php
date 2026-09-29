@extends('layouts.app')
@section('title', $coach->exists ? 'Modifier le coach' : 'Nouveau coach')

@section('content')
<div class="top"><div><div class="eyebrow"><a href="{{ route('admin.coachs.index') }}">← Coachs</a></div><h1>{{ $coach->exists ? $coach->nom : 'Nouveau coach' }}</h1></div></div>

<form method="POST" action="{{ $coach->exists ? route('admin.coachs.update', $coach) : route('admin.coachs.store') }}" class="card form-card">
    @csrf
    @if($coach->exists) @method('PUT') @endif
    <div class="grid g2">
        <label class="fld" for="nom">Nom *<input id="nom" name="nom" value="{{ old('nom', $coach->nom) }}" required maxlength="100"></label>
        <label class="fld" for="telephone">Téléphone<input id="telephone" name="telephone" value="{{ old('telephone', $coach->telephone) }}"></label>
        <label class="fld" for="specialite">Spécialité<input id="specialite" name="specialite" value="{{ old('specialite', $coach->specialite) }}" placeholder="ex. musculation, zumba, boxe"></label>
        <label class="fld" for="commission_pct">Commission sur le coaching personnel (%)<input id="commission_pct" type="number" name="commission_pct" min="0" max="100" value="{{ old('commission_pct', $coach->commission_pct) }}" required><span class="aide">Part du prix de chaque séance faite qui revient au coach.</span></label>
    </div>
    <label class="check" for="actif"><input id="actif" type="checkbox" name="actif" value="1" @checked(old('actif', $coach->actif))> Coach actif</label>
    <div class="actions"><button class="btn">Enregistrer</button><a href="{{ route('admin.coachs.index') }}" class="btn ghost">Annuler</a></div>
</form>
@endsection
