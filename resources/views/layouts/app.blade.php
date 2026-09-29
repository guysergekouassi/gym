<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Accueil') · {{ config('salle.nom') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/gymflow.css') }}">
</head>
<body class="gf">
@php
    $user = auth()->user();
    $courant = fn (string ...$routes) => request()->routeIs(...$routes) ? 'page' : 'false';
    $initiales = collect(explode(' ', $user->name))->map(fn ($m) => mb_substr($m, 0, 1))->take(2)->implode('');
    $aFaire = \App\Models\Message::aEnvoyer()->count();
@endphp
<div class="offline" data-hors-ligne hidden role="status">Connexion perdue. Les pages ne se mettront pas à jour tant qu'elle n'est pas revenue.</div>
<div class="app">
    <aside class="side">
        <div class="brand">
            <div class="logo">@include('partials.icone', ['nom' => 'haltere', 'trait' => 2.2])</div>
            <div><b>{{ config('salle.nom') }}</b><small>{{ config('salle.adresse') }}</small></div>
        </div>

        <button type="button" class="search-trigger" data-ouvrir-recherche>
            @include('partials.icone', ['nom' => 'loupe', 'taille' => 16])<span>Rechercher…</span><kbd>Ctrl K</kbd>
        </button>

        @if($user->isAdmin())
            <nav class="nav" aria-label="Navigation principale">
                <a href="{{ route('dashboard') }}" aria-current="{{ $courant('dashboard') }}">@include('partials.icone', ['nom' => 'tableau'])Tableau de bord</a>
                <a href="{{ route('taches.index') }}" aria-current="{{ $courant('taches.*') }}">@include('partials.icone', ['nom' => 'liste'])À faire @if($aFaire)<span class="badge">{{ $aFaire }}</span>@endif</a>
                <a href="{{ route('passages.index') }}" aria-current="{{ $courant('passages.*') }}">@include('partials.icone', ['nom' => 'passages'])Passages</a>
                <a href="{{ route('clients.index') }}" aria-current="{{ $courant('clients.*') }}">@include('partials.icone', ['nom' => 'clients'])Clients</a>
                <a href="{{ route('prospects.index') }}" aria-current="{{ $courant('prospects.*') }}">@include('partials.icone', ['nom' => 'prospect'])Prospects</a>
                <a href="{{ route('planning.index') }}" aria-current="{{ $courant('planning.*') }}">@include('partials.icone', ['nom' => 'calendrier'])Planning des cours</a>
                <div class="label">Gestion</div>
                <a href="{{ route('admin.caisses.index') }}" aria-current="{{ $courant('admin.caisses.*') }}">@include('partials.icone', ['nom' => 'caisse'])Caisses</a>
                <a href="{{ route('admin.caissiers.index') }}" aria-current="{{ $courant('admin.caissiers.*') }}">@include('partials.icone', ['nom' => 'equipe'])Caissières</a>
                <a href="{{ route('admin.formules.index') }}" aria-current="{{ $courant('admin.formules.*', 'admin.promos.*') }}">@include('partials.icone', ['nom' => 'etiquette'])Formules et promos</a>
                <a href="{{ route('admin.coachs.index') }}" aria-current="{{ $courant('admin.coachs.*', 'admin.cours.*') }}">@include('partials.icone', ['nom' => 'coach'])Coachs et cours</a>
                <a href="{{ route('admin.produits.index') }}" aria-current="{{ $courant('admin.produits.*') }}">@include('partials.icone', ['nom' => 'boutique'])Bar et boutique</a>
                <a href="{{ route('admin.salles.index') }}" aria-current="{{ $courant('admin.salles.*', 'admin.lecteurs.*') }}">@include('partials.icone', ['nom' => 'salle'])Salles et lecteurs</a>
                <div class="label">Analyse</div>
                <a href="{{ route('admin.rapports') }}" aria-current="{{ $courant('admin.rapports') }}">@include('partials.icone', ['nom' => 'graphique'])Rapports</a>
                <a href="{{ route('admin.campagnes.index') }}" aria-current="{{ $courant('admin.campagnes.*') }}">@include('partials.icone', ['nom' => 'message'])Campagnes</a>
                <a href="{{ route('admin.exports') }}" aria-current="{{ $courant('admin.exports*') }}">@include('partials.icone', ['nom' => 'export'])Exports Excel</a>
                <a href="{{ route('admin.journal') }}" aria-current="{{ $courant('admin.journal') }}">@include('partials.icone', ['nom' => 'journal'])Journal des actions</a>
            </nav>
        @else
            <div class="space">Espace caisse<span>{{ $user->caisse?->nom ?? 'Aucune caisse attribuée' }}</span></div>
            <nav class="nav" aria-label="Navigation principale">
                <a href="{{ route('caisse.index') }}" aria-current="{{ $courant('caisse.index') }}">@include('partials.icone', ['nom' => 'caisse'])Caisse</a>
                <a href="{{ route('taches.index') }}" aria-current="{{ $courant('taches.*') }}">@include('partials.icone', ['nom' => 'liste'])À faire @if($aFaire)<span class="badge">{{ $aFaire }}</span>@endif</a>
                <a href="{{ route('clients.index') }}" aria-current="{{ $courant('clients.*') }}">@include('partials.icone', ['nom' => 'clients'])Clients</a>
                <a href="{{ route('prospects.index') }}" aria-current="{{ $courant('prospects.*') }}">@include('partials.icone', ['nom' => 'prospect'])Prospects</a>
                <a href="{{ route('planning.index') }}" aria-current="{{ $courant('planning.*') }}">@include('partials.icone', ['nom' => 'calendrier'])Planning des cours</a>
                <a href="{{ route('passages.index') }}" aria-current="{{ $courant('passages.*') }}">@include('partials.icone', ['nom' => 'passages'])Passages du jour</a>
                <a href="{{ route('caisse.cloture') }}" aria-current="{{ $courant('caisse.cloture') }}">@include('partials.icone', ['nom' => 'cadenas'])Clôturer la caisse</a>
            </nav>
        @endif

        <div class="side-foot">
            <div class="me">
                <div class="av">{{ $initiales }}</div>
                <div>
                    <strong>{{ $user->name }}</strong><br>
                    <a href="{{ route('compte.edit') }}" class="me-link">Mon compte</a>
                    <form method="POST" action="{{ route('logout') }}" style="display:inline">
                        @csrf
                        <button class="logout">Déconnexion</button>
                    </form>
                </div>
            </div>
        </div>
    </aside>

    <main class="content">
        <noscript>
            @if(session('succes'))<div class="flash ok" role="status">{{ session('succes') }}</div>@endif
            @if(session('erreur'))<div class="flash ko" role="alert">{{ session('erreur') }}</div>@endif
            @if($errors->any())<div class="flash ko" role="alert"><ul>@foreach($errors->all() as $erreur)<li>{{ $erreur }}</li>@endforeach</ul></div>@endif
        </noscript>

        @yield('content')
    </main>
</div>

@include('partials.alertes')
@include('partials.modale')
@include('partials.recherche')
@stack('scripts')
<script>
(() => {
    const bandeau = document.querySelector('[data-hors-ligne]');
    const maj = () => { bandeau.hidden = navigator.onLine; };
    addEventListener('online', maj); addEventListener('offline', maj); maj();

    // Barre de chargement pendant le passage d'une page à l'autre
    const barre = () => { if (!document.querySelector('.chargement')) { const b = document.createElement('div'); b.className = 'chargement'; document.body.append(b); } };
    document.addEventListener('click', (e) => {
        const a = e.target.closest('a[href]');
        if (a && !a.target && !e.ctrlKey && !e.metaKey && !a.hasAttribute('download') && a.origin === location.origin && !a.getAttribute('href').startsWith('#')) barre();
    });
    document.addEventListener('submit', (e) => { setTimeout(() => { if (!e.defaultPrevented) barre(); }, 0); });
    addEventListener('pageshow', () => document.querySelectorAll('.chargement').forEach((b) => b.remove()));
})();
</script>
</body>
</html>
