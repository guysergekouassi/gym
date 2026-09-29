@extends('layouts.auth')
@section('title', 'Connexion')

@section('content')
<div>
    <h2>Heureux de vous revoir !</h2>
    <p class="muted" style="margin:6px 0 0">Connectez-vous pour accéder à votre espace de gestion.</p>
</div>

@if(session('succes'))
    <div class="flash ok" role="status">{{ session('succes') }}</div>
@endif
@error('email')
    <div class="flash ko" role="alert">{{ $message }}</div>
@enderror

<form method="POST" action="{{ route('login') }}" class="grid" style="gap:14px">
    @csrf
    <label class="fld" for="email">Adresse e-mail
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
    </label>
    <label class="fld" for="password">Mot de passe
        <span class="pw">
            <input id="password" type="password" name="password" required autocomplete="current-password" style="width:100%">
            <button type="button" data-voir aria-label="Afficher le mot de passe" aria-pressed="false">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
        </span>
    </label>
    <div class="auth-links">
        <label class="check" for="remember" style="min-height:0"><input id="remember" type="checkbox" name="remember"> Rester connecté</label>
        <a href="{{ route('password.request') }}">Mot de passe oublié ?</a>
    </div>
    <button class="btn xl">Se connecter</button>
</form>

<p class="auth-foot">Vous êtes membre ? <a href="{{ route('membre.connexion') }}">Accédez à votre espace membre</a></p>

<script>
document.querySelector('[data-voir]').addEventListener('click', (e) => {
    const b = e.currentTarget, champ = document.getElementById('password');
    const voir = champ.type === 'password';
    champ.type = voir ? 'text' : 'password';
    b.setAttribute('aria-pressed', voir); b.setAttribute('aria-label', voir ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
});
</script>
@endsection
