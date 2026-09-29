@extends('layouts.auth')
@section('title', 'Accès non autorisé')

@section('content')
<div>
    <h2>Cette page n'est pas accessible</h2>
    <p class="muted" style="margin:6px 0 0">
        @auth
            Vous êtes connecté en tant que <strong>{{ auth()->user()->name }}</strong> ({{ \App\Models\User::ROLES[auth()->user()->role] ?? auth()->user()->role }}).
            {{ $exception->getMessage() && $exception->getMessage() !== 'Accès non autorisé.' ? $exception->getMessage() : 'Cette page est réservée à un autre espace.' }}
        @else
            Connectez-vous pour continuer.
        @endauth
    </p>
</div>

@auth
    <a href="{{ url('/') }}" class="btn xl">Retourner à mon espace</a>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="btn ghost xl">Se déconnecter et changer de compte</button>
    </form>
@else
    <a href="{{ route('login') }}" class="btn xl">Se connecter</a>
@endauth
@endsection
