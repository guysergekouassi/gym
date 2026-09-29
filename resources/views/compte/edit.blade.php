@extends('layouts.app')
@section('title', 'Mon compte')

@section('content')
<div class="top"><div><div class="eyebrow">{{ \App\Models\User::ROLES[auth()->user()->role] ?? '' }}</div><h1>Mon compte</h1></div></div>

<form method="POST" action="{{ route('compte.update') }}" class="card form-card">
    @csrf @method('PUT')
    <div class="grid g2">
        <label class="fld" for="name">Nom affiché<input id="name" name="name" value="{{ old('name', auth()->user()->name) }}" required maxlength="100"></label>
        <label class="fld" for="email">E-mail de connexion<input id="email" type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required></label>
        <label class="fld" for="password">Nouveau mot de passe<input id="password" type="password" name="password" minlength="8" autocomplete="new-password"><span class="aide">Laissez vide pour ne pas le changer. 8 caractères minimum.</span></label>
        <label class="fld" for="password_confirmation">Confirmer le nouveau mot de passe<input id="password_confirmation" type="password" name="password_confirmation" minlength="8" autocomplete="new-password"></label>
    </div>
    <label class="fld" for="mot_de_passe_actuel" style="max-width:360px">Mot de passe actuel (pour confirmer)<input id="mot_de_passe_actuel" type="password" name="mot_de_passe_actuel" required autocomplete="current-password"></label>
    <div class="actions"><button class="btn">Enregistrer</button></div>
</form>
@endsection
