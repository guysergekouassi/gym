@extends('layouts.auth')
@section('title', 'Mot de passe oublié')

@section('content')
<div>
    <h2>Mot de passe oublié</h2>
    <p class="muted" style="margin:6px 0 0">Saisissez votre adresse : vous recevrez un lien pour choisir un nouveau mot de passe.</p>
</div>

@if(session('succes'))
    <div class="flash ok" role="status">{{ session('succes') }}</div>
@endif
@error('email')
    <div class="flash ko" role="alert">{{ $message }}</div>
@enderror

<form method="POST" action="{{ route('password.email') }}" class="grid" style="gap:14px">
    @csrf
    <label class="fld" for="email">Adresse e-mail
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
    </label>
    <button class="btn xl">Envoyer le lien</button>
</form>

<p class="hint-card" style="margin:0">Pas d'e-mail reçu ? Une caissière peut demander à l'administrateur de changer son mot de passe depuis la page Caissières.</p>
<p class="auth-foot"><a href="{{ route('login') }}">← Retour à la connexion</a></p>
@endsection
