@php
    $user = auth()->user();
    $initiales = collect(explode(' ', $user->name))->map(fn ($m) => mb_substr($m, 0, 1))->take(2)->implode('');
    $lien = fn (string $route, string $icone, string $texte, ?string $motif = null) =>
        '<a href="'.route($route).'" class="'.(request()->routeIs($motif ?? $route) ? 'actif' : '').'">'
        .view('partials.icone', ['nom' => $icone])->render().e($texte).'</a>';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titre', 'SalonFlow') — {{ config('salon.nom') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="@yield('classe-body')">
<div class="app">
    <aside class="sidebar">
        <div class="marque">
            <div class="logo">S</div>
            <div><strong>SalonFlow</strong><span>Caisse salon de coiffure</span></div>
        </div>
        <div class="salon">
            <small>Salon</small>
            <b>{{ config('salon.nom') }}</b>
        </div>
        <nav>
            @if($user->isGerante())
                <div class="titre">Tableau de bord</div>
                {!! $lien('dashboard', 'tableau', 'Tableau de bord') !!}
                {!! $lien('ventes.index', 'liste', 'Historique des ventes') !!}
                <div class="titre">Ventes</div>
                {!! $lien('caisse.index', 'caisse', 'Nouvelle vente') !!}
                <div class="titre">Catalogue</div>
                {!! $lien('services.index', 'ciseaux', 'Services & prix', 'services.*') !!}
                <div class="titre">Administration</div>
                {!! $lien('comptes.index', 'utilisateurs', 'Comptes assistantes') !!}
            @else
                <div class="titre">Ventes</div>
                {!! $lien('caisse.index', 'caisse', 'Nouvelle vente') !!}
                <div class="titre">Mes résultats</div>
                {!! $lien('mes-kpi', 'tableau', 'Mes KPI') !!}
            @endif
        </nav>
        <div class="pied">Fonctionne hors ligne · v1.0</div>
    </aside>

    <div class="principal">
        <header class="entete">
            <button class="burger" type="button" onclick="basculerMenu()" aria-label="Menu">@include('partials.icone', ['nom' => 'menu'])</button>
            <h1>@yield('titre', 'SalonFlow')</h1>
            <span class="fil">/ {{ config('salon.nom') }}</span>
            <div class="espace"></div>
            <div class="horloge" id="horloge">{{ now()->format('H:i:s') }}</div>
            <div class="utilisateur" id="utilisateur">
                <button type="button" onclick="this.parentElement.classList.toggle('ouvert')">
                    <span class="avatar">{{ mb_strtoupper($initiales) }}</span> {{ $user->name }} ▾
                </button>
                <div class="menu">
                    <div class="role">{{ $user->isGerante() ? 'Gérante' : 'Assistante' }} · {{ $user->email }}</div>
                    <a href="{{ route('mot-de-passe.edit') }}">@include('partials.icone', ['nom' => 'cle']) Mon mot de passe</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit">@include('partials.icone', ['nom' => 'sortie']) Se déconnecter</button>
                    </form>
                </div>
            </div>
        </header>

        <main class="contenu">
            @unless(View::hasSection('sans-alertes'))
                @if(session('succes'))<div class="alerte alerte-succes">{{ session('succes') }}</div>@endif
                @if(session('erreur'))<div class="alerte alerte-erreur">{{ session('erreur') }}</div>@endif
            @endunless
            @yield('contenu')
        </main>
    </div>
</div>

<script>
    function basculerMenu() {
        document.body.classList.toggle(window.innerWidth > 1100 ? 'menu-replie' : 'menu-ouvert');
    }
    document.addEventListener('click', (e) => {
        const menu = document.getElementById('utilisateur');
        if (!menu.contains(e.target)) menu.classList.remove('ouvert');
    });
    setInterval(() => {
        document.getElementById('horloge').textContent = new Date().toLocaleTimeString('fr-FR');
    }, 1000);
</script>
@stack('scripts')
</body>
</html>
