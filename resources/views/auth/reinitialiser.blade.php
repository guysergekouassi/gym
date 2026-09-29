@extends('layouts.auth')
@section('title', 'Nouveau mot de passe')

@section('content')
<div>
    <h2>Nouveau mot de passe</h2>
    <p class="muted" style="margin:6px 0 0">8 caractères minimum.</p>
</div>

@if($errors->any())
    <div class="flash ko" role="alert">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route('password.update') }}" class="grid" style="gap:14px">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <label class="fld" for="email">Adresse e-mail
        <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required>
    </label>
    <label class="fld" for="password">Nouveau mot de passe
        <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password">
    </label>
    <label class="fld" for="password_confirmation">Confirmer
        <input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
    </label>
    <button class="btn xl">Enregistrer le mot de passe</button>
</form>
@endsection
