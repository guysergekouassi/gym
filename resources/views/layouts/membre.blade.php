<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0F9F8C">
    <title>@yield('title', 'Mon espace') · {{ config('salle.nom') }}</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/gymflow.css') }}">
    <style>
        body.m { background: var(--bg); min-height: 100vh; padding-bottom: calc(76px + env(safe-area-inset-bottom, 0px)) }
        .m-wrap { max-width: 520px; margin: 0 auto; padding: 16px; display: flex; flex-direction: column; gap: 14px }
        .m-top { display: flex; justify-content: space-between; align-items: center; gap: 10px }
        .m-top b { font-family: var(--f-display); font-size: 22px; letter-spacing: .6px; text-transform: uppercase; display: flex; align-items: center; gap: 8px }
        .m-top .logo { width: 32px; height: 32px }
        .m-nav { position: fixed; left: 0; right: 0; bottom: 0; background: rgb(255 255 255 / .8); backdrop-filter: blur(16px); border-top: 1px solid var(--line); display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); padding: 6px 8px calc(6px + env(safe-area-inset-bottom, 0px)); z-index: 20 }
        .gf .m-nav a { display: flex; flex-direction: column; align-items: center; gap: 2px; font-size: 11px; font-weight: 700; color: var(--muted); padding: 6px 0; min-height: 52px; justify-content: center; text-decoration: none }
        .gf .m-nav a[aria-current="page"] { color: var(--accent-ink) }
        .hero-m { background: var(--grad); color: #fff; border-radius: 26px; box-shadow: var(--glow); padding: 20px; display: flex; flex-direction: column; gap: 4px }
        .hero-m.ko { background: linear-gradient(135deg, #F0647A, #D0344B) } .hero-m.gel { background: linear-gradient(135deg, #8B7CFB, #5B4CF5) }
        .hero-m .num { font-size: 58px }
        .qr-m { background: #fff; border-radius: 18px; padding: 14px; width: min(230px, 70%); margin: -30px auto 0; box-shadow: 0 12px 30px rgb(0 0 0 / .15); aspect-ratio: 1 }
        .qr-m img { width: 100%; height: 100%; display: block }
    </style>
</head>
<body class="gf m">
<div class="m-wrap">
    <div class="m-top">
        <b><span class="logo">@include('partials.icone', ['nom' => 'haltere', 'trait' => 2.2])</span>{{ config('salle.nom') }}</b>
        @isset($membre)
            <form method="POST" action="{{ route('membre.deconnexion') }}">@csrf<button class="logout" style="color:var(--muted)">Se déconnecter</button></form>
        @endisset
    </div>

    <noscript>
        @if(session('succes'))<div class="flash ok" role="status">{{ session('succes') }}</div>@endif
        @if($errors->any())<div class="flash ko" role="alert">{{ $errors->first() }}</div>@endif
    </noscript>

    @yield('content')
</div>

@isset($membre)
<nav class="m-nav" aria-label="Espace membre">
    <a href="{{ route('membre.accueil') }}" aria-current="{{ request()->routeIs('membre.accueil') ? 'page' : 'false' }}">@include('partials.icone', ['nom' => 'carte', 'taille' => 22])Mon accès</a>
    <a href="{{ route('membre.cours') }}" aria-current="{{ request()->routeIs('membre.cours') ? 'page' : 'false' }}">@include('partials.icone', ['nom' => 'calendrier', 'taille' => 22])Cours</a>
    <a href="{{ route('membre.progression') }}" aria-current="{{ request()->routeIs('membre.progression') ? 'page' : 'false' }}">@include('partials.icone', ['nom' => 'graphique', 'taille' => 22])Progrès</a>
    <a href="{{ route('membre.historique') }}" aria-current="{{ request()->routeIs('membre.historique') ? 'page' : 'false' }}">@include('partials.icone', ['nom' => 'journal', 'taille' => 22])Historique</a>
</nav>
@endisset

@include('partials.alertes')
<script>
if ('serviceWorker' in navigator) { navigator.serviceWorker.register(@json(asset('sw.js'))).catch(() => {}); }
</script>
</body>
</html>
