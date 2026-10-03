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
    $alertes ??= collect();
@endphp

<div class="min-h-full lg:pl-64">
    {{-- Barre latérale --}}
    <div data-sidebar-backdrop class="fixed inset-0 z-40 hidden bg-ink-950/60 backdrop-blur-sm lg:hidden"></div>
    <aside data-sidebar class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col bg-gradient-to-b from-ink-900 to-ink-950 px-4 py-6 transition-transform duration-200 lg:translate-x-0">
        <a href="{{ url('/') }}" class="flex items-center gap-2.5 px-2">
            <x-logo class="h-9 w-9"/>
            <span class="truncate text-2xl font-extrabold tracking-tight text-white">{{ config('salle.nom') }}</span>
        </a>

        <nav class="defilement-discret -mr-2 mt-6 flex-1 overflow-y-auto pr-2">
            <x-menu-section nom="quotidien" titre="Quotidien">
                @unless($user->isAdmin())
                    <a href="{{ route('journee') }}" class="{{ $actif('journee') }}"><x-icon name="home"/> Tableau de bord</a>
                @endunless
                <a href="{{ route('caisse.index') }}" class="{{ $actif('caisse.*', 'recus.*') }}"><x-icon name="cash"/> Caisse</a>
                <a href="{{ route('clients.index') }}" class="{{ $actif('clients.*') }}"><x-icon name="users"/> Clients</a>
                <a href="{{ route('accueil.index') }}" target="_blank" rel="noopener" class="nav-link"><x-icon name="fingerprint"/> Écran d'accueil <x-icon name="external" class="ml-auto size-4 opacity-50"/></a>
            </x-menu-section>

            @if($user->isAdmin())
                <x-menu-section nom="pilotage" titre="Pilotage">
                    <a href="{{ route('dashboard') }}" class="{{ $actif('dashboard') }}"><x-icon name="home"/> Tableau de bord</a>
                    <a href="{{ route('admin.paiements.index') }}" class="{{ $actif('admin.paiements.*') }}"><x-icon name="receipt"/> Encaissements</a>
                </x-menu-section>

                <x-menu-section nom="administration" titre="Administration">
                    <a href="{{ route('admin.formules.index') }}" class="{{ $actif('admin.formules.*') }}"><x-icon name="tag"/> Formules &amp; tarifs</a>
                    <a href="{{ route('admin.utilisateurs.index') }}" class="{{ $actif('admin.utilisateurs.*') }}"><x-icon name="shield"/> Utilisateurs</a>
                    <a href="{{ route('admin.pointeuses.index') }}" class="{{ $actif('admin.pointeuses.*') }}"><x-icon name="fingerprint"/> Pointeuses</a>
                    <a href="{{ route('admin.parametres.edit') }}" class="{{ $actif('admin.parametres.*') }}"><x-icon name="settings"/> Paramètres</a>
                </x-menu-section>
            @endif
        </nav>

        {{-- Profil (menu déroulant vers le haut) --}}
        <details class="group relative mt-4 border-t border-white/10 pt-4">
            <summary class="flex cursor-pointer list-none items-center gap-3 rounded-xl px-2 py-2 hover:bg-white/5 [&::-webkit-details-marker]:hidden">
                <span class="inline-flex size-10 items-center justify-center rounded-full bg-sky-600 text-base font-bold text-white">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-white">{{ $user->name }}</span>
                    <span class="block truncate text-xs text-slate-400">{{ $user->isAdmin() ? 'Administrateur' : 'Caissier' }}</span>
                </span>
                <x-icon name="chevron-down" class="size-4 text-slate-400 transition group-open:rotate-180"/>
            </summary>
            <div class="absolute inset-x-0 bottom-full mb-2 overflow-hidden rounded-xl bg-white text-sm shadow-xl ring-1 ring-slate-900/10">
                <a href="{{ route('mot-de-passe.edit') }}" class="flex items-center gap-2 px-4 py-3 text-slate-700 hover:bg-slate-50"><x-icon name="key" class="size-4"/> Mot de passe</a>
                <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-100">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-2 px-4 py-3 text-red-600 hover:bg-red-50"><x-icon name="logout" class="size-4"/> Déconnexion</button>
                </form>
            </div>
        </details>
    </aside>

    {{-- Barre du haut --}}
    <header class="sticky top-0 z-30 flex h-[4.5rem] items-center gap-3 border-b border-slate-200/70 bg-[#f3f6fa]/90 px-4 backdrop-blur sm:px-6 lg:px-8">
        <button type="button" data-sidebar-toggle class="-ml-1 rounded-lg p-2 text-slate-600 hover:bg-white lg:hidden" aria-label="Ouvrir le menu">
            <x-icon name="menu" class="size-6"/>
        </button>

        <form method="GET" action="{{ route('clients.index') }}" class="relative w-full max-w-md" role="search">
            <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-5 -translate-y-1/2 text-slate-400"/>
            <input type="search" name="q" maxlength="100" value="{{ request()->routeIs('clients.index') ? request('q') : '' }}"
                   placeholder="Rechercher un client, un téléphone…"
                   class="w-full rounded-xl border-0 bg-white py-2.5 pl-11 pr-4 text-sm shadow-sm ring-1 ring-slate-200 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500">
        </form>

        <div class="ml-auto flex items-center gap-3">
            {{-- Alertes : abonnements qui expirent bientôt --}}
            <details class="group relative">
                <summary class="relative flex cursor-pointer list-none rounded-full p-2 text-slate-600 hover:bg-white [&::-webkit-details-marker]:hidden" aria-label="Alertes">
                    <x-icon name="bell" class="size-6"/>
                    @if($alertes->isNotEmpty())
                        <span class="absolute right-1 top-1 inline-flex min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-4 text-white">{{ $alertes->count() }}</span>
                    @endif
                </summary>
                <div class="absolute right-0 mt-2 w-80 overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-slate-900/10">
                    <p class="border-b border-slate-100 px-4 py-3 text-sm font-semibold">Abonnements qui expirent bientôt</p>
                    <ul class="max-h-80 divide-y divide-slate-100 overflow-y-auto text-sm">
                        @forelse($alertes->take(8) as $a)
                            <li>
                                <a href="{{ route('caisse.index', ['client_id' => $a->client_id]) }}" class="flex items-center justify-between gap-2 px-4 py-2.5 hover:bg-slate-50">
                                    <span class="truncate">{{ $a->client->nom_complet }}</span>
                                    <span class="{{ $a->joursRestants() <= 2 ? 'pill-red' : 'pill-amber' }}">{{ $a->joursRestants() }} j</span>
                                </a>
                            </li>
                        @empty
                            <li class="px-4 py-6 text-center text-slate-400">Rien à signaler</li>
                        @endforelse
                    </ul>
                    @if($alertes->isNotEmpty())
                        <a href="{{ route('caisse.index', ['onglet' => 'renouvellement']) }}" class="block border-t border-slate-100 px-4 py-2.5 text-center text-xs font-semibold text-brand-600 hover:bg-slate-50">Voir les renouvellements →</a>
                    @endif
                </div>
            </details>

            <span class="hidden h-8 w-px bg-slate-200 sm:block"></span>
            <div class="hidden items-center gap-3 sm:flex">
                <span class="inline-flex size-10 items-center justify-center rounded-full bg-sky-600 text-sm font-bold text-white">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                <span class="leading-tight">
                    <span class="block text-sm font-semibold text-slate-900">{{ $user->name }}</span>
                    <span class="block text-xs text-slate-500">{{ $user->isAdmin() ? 'Administrateur' : 'Caissier' }}</span>
                </span>
            </div>
        </div>
    </header>

    <main class="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <div class="mx-auto max-w-[96rem]">
            <x-alertes/>
            @yield('content')
        </div>
    </main>
</div>
</body>
</html>
