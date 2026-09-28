<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Accueil') · {{ config('salle.nom') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">
@php
    $lien = fn ($route) => request()->routeIs($route) ? 'text-emerald-400 font-semibold' : 'hover:text-emerald-300';
@endphp
<nav class="bg-slate-900 text-white">
    <div class="max-w-7xl mx-auto px-4 h-14 flex items-center gap-6 text-sm">
        <span class="font-bold text-base">{{ config('salle.nom') }}</span>
        @if(auth()->user()->isAdmin())
            <a href="{{ route('dashboard') }}" class="{{ $lien('dashboard') }}">Tableau de bord</a>
        @endif
        <a href="{{ route('caisse.index') }}" class="{{ $lien('caisse.*') }}">Caisse</a>
        <a href="{{ route('clients.index') }}" class="{{ $lien('clients.*') }}">Clients</a>
        <a href="{{ route('accueil.index') }}" target="_blank" class="hover:text-emerald-300">Écran d'accueil ↗</a>
        <div class="ml-auto flex items-center gap-3">
            <span class="text-slate-300">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="underline hover:text-emerald-300">Déconnexion</button>
            </form>
        </div>
    </div>
</nav>

<main class="max-w-7xl mx-auto px-4 py-6">
    @if(session('succes'))
        <div class="mb-4 rounded-lg bg-emerald-100 text-emerald-800 px-4 py-3">{{ session('succes') }}</div>
    @endif
    @if(session('erreur'))
        <div class="mb-4 rounded-lg bg-red-100 text-red-800 px-4 py-3">{{ session('erreur') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-lg bg-red-100 text-red-800 px-4 py-3">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $erreur)<li>{{ $erreur }}</li>@endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>

@stack('scripts')
</body>
</html>
