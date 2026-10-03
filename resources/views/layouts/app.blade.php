<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Accueil') · {{ config('salle.nom') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
@php
    $user = auth()->user();
    $actif = fn (string ...$routes) => request()->routeIs(...$routes) ? 'nav-link active' : 'nav-link';
@endphp

<div class="min-h-full lg:pl-72">
    {{-- Barre latérale --}}
    <div data-sidebar-backdrop class="fixed inset-0 z-40 hidden bg-ink-950/60 backdrop-blur-sm lg:hidden"></div>
    <aside data-sidebar class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col bg-ink-950 px-4 py-5 transition-transform duration-200 lg:translate-x-0">
        <div class="flex items-center gap-3 px-2">
            <x-logo/>
            <div class="min-w-0">
                <p class="truncate text-lg font-bold text-white">{{ config('salle.nom') }}</p>
                <p class="text-xs text-slate-400">Gestion de la salle</p>
            </div>
        </div>

        <nav class="mt-8 flex-1 overflow-y-auto">
            <p class="nav-section">Quotidien</p>
            <a href="{{ route('caisse.index') }}" class="{{ $actif('caisse.*', 'recus.*') }}"><x-icon name="cash"/> Caisse</a>
            <a href="{{ route('clients.index') }}" class="{{ $actif('clients.*') }}"><x-icon name="users"/> Clients</a>
            <a href="{{ route('accueil.index') }}" target="_blank" rel="noopener" class="nav-link"><x-icon name="tv"/> Écran d'accueil <x-icon name="external" class="ml-auto size-4 opacity-60"/></a>

            @if($user->isAdmin())
                <p class="nav-section">Pilotage</p>
                <a href="{{ route('dashboard') }}" class="{{ $actif('dashboard') }}"><x-icon name="dashboard"/> Tableau de bord</a>
                <a href="{{ route('admin.paiements.index') }}" class="{{ $actif('admin.paiements.*') }}"><x-icon name="receipt"/> Encaissements</a>

                <p class="nav-section">Administration</p>
                <a href="{{ route('admin.formules.index') }}" class="{{ $actif('admin.formules.*') }}"><x-icon name="tag"/> Formules &amp; tarifs</a>
                <a href="{{ route('admin.utilisateurs.index') }}" class="{{ $actif('admin.utilisateurs.*') }}"><x-icon name="shield"/> Utilisateurs</a>
                <a href="{{ route('admin.pointeuses.index') }}" class="{{ $actif('admin.pointeuses.*') }}"><x-icon name="signal"/> Pointeuses</a>
            @endif
        </nav>

        <div class="mt-4 rounded-2xl bg-white/5 p-3">
            <div class="flex items-center gap-3">
                <span class="inline-flex size-10 items-center justify-center rounded-full bg-brand-500/20 text-sm font-bold text-brand-300">
                    {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-white">{{ $user->name }}</p>
                    <p class="truncate text-xs text-slate-400">{{ \App\Models\User::ROLES[$user->role] ?? $user->role }}</p>
                </div>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-2">
                <a href="{{ route('mot-de-passe.edit') }}" class="inline-flex items-center justify-center gap-1 rounded-lg bg-white/5 px-1.5 py-2 text-[11px] font-medium text-slate-300 hover:bg-white/10 hover:text-white">
                    <x-icon name="key" class="size-4"/> <span class="whitespace-nowrap">Mot de passe</span>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="inline-flex w-full items-center justify-center gap-1 rounded-lg bg-white/5 px-1.5 py-2 text-[11px] font-medium text-slate-300 hover:bg-red-500/20 hover:text-red-200">
                        <x-icon name="logout" class="size-4"/> Quitter
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Barre du haut --}}
    <header class="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-slate-200/70 bg-white/80 px-4 backdrop-blur sm:px-6 lg:px-8">
        <button type="button" data-sidebar-toggle class="-ml-1 rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Ouvrir le menu">
            <x-icon name="menu" class="size-6"/>
        </button>
        <div class="flex items-center gap-2 text-sm text-slate-500">
            <x-icon name="calendar" class="size-4"/>
            <span class="first-letter:uppercase">{{ now()->translatedFormat('l j F Y') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <a href="{{ route('caisse.index') }}" class="btn-primary btn-sm hidden sm:inline-flex"><x-icon name="plus" class="size-4"/> Encaisser</a>
            <a href="{{ route('clients.create') }}" class="btn-light btn-sm hidden sm:inline-flex"><x-icon name="user-plus" class="size-4"/> Nouveau client</a>
        </div>
    </header>

    <main class="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <div class="mx-auto max-w-7xl">
            <x-alertes/>
            @yield('content')
        </div>
    </main>
</div>
</body>
</html>
