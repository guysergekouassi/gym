@extends('layouts.app')
@section('title', $promo->exists ? 'Modifier le code' : 'Nouveau code promo')

@php use App\Models\CodePromo; @endphp

@section('content')
<div class="top"><div><div class="eyebrow"><a href="{{ route('admin.promos.index') }}">← Codes promo</a></div><h1>{{ $promo->exists ? $promo->code : 'Nouveau code promo' }}</h1></div></div>

<form method="POST" action="{{ $promo->exists ? route('admin.promos.update', $promo) : route('admin.promos.store') }}" class="card form-card">
    @csrf
    @if($promo->exists) @method('PUT') @endif
    <div class="grid g2">
        <label class="fld" for="code">Code *<input id="code" name="code" value="{{ old('code', $promo->code) }}" required maxlength="30" style="text-transform:uppercase" placeholder="ex. RENTREE25"><span class="aide">Lettres, chiffres et tirets, sans espace.</span></label>
        <label class="fld" for="type">Type de remise
            <select id="type" name="type">@foreach(CodePromo::TYPES as $v => $l)<option value="{{ $v }}" @selected(old('type', $promo->type) === $v)>{{ $l }}</option>@endforeach</select>
        </label>
        <label class="fld" for="valeur">Valeur *<input id="valeur" type="number" name="valeur" min="1" value="{{ old('valeur', $promo->valeur) }}" required><span class="aide">En % ou en FCFA selon le type.</span></label>
        <label class="fld" for="expire_le">Valable jusqu'au<input id="expire_le" type="date" name="expire_le" value="{{ old('expire_le', $promo->expire_le?->toDateString()) }}"></label>
        <label class="fld" for="utilisations_max">Nombre maximum d'utilisations<input id="utilisations_max" type="number" name="utilisations_max" min="1" value="{{ old('utilisations_max', $promo->utilisations_max) }}" placeholder="Illimité"></label>
    </div>
    <label class="check" for="actif"><input id="actif" type="checkbox" name="actif" value="1" @checked(old('actif', $promo->actif))> Code actif</label>
    <div class="actions"><button class="btn">Enregistrer</button><a href="{{ route('admin.promos.index') }}" class="btn ghost">Annuler</a></div>
</form>
@endsection
