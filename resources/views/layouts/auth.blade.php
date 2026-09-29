<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Connexion') · {{ config('salle.nom') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/gymflow.css') }}">
</head>
<body class="gf auth">
<div class="auth-wrap">
    <section class="auth-hero" aria-label="Présentation">
        <h1>Avec {{ config('salle.nom') }}, gérez votre salle <span>en quelques clics.</span></h1>
        <p>Pointage par empreinte ou par carte, caisse en FCFA avec Mobile Money, relances WhatsApp et suivi des membres, au même endroit.</p>
        <div class="illus" aria-hidden="true">
            <div class="tuile grande">@include('partials.icone', ['nom' => 'haltere', 'trait' => 2.4])</div>
            <div class="tuile petite">@include('partials.icone', ['nom' => 'empreinte', 'trait' => 1.8])</div>
            <div class="annot a1">
                <svg width="46" height="30" viewBox="0 0 46 30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-dasharray="3 3"><path d="M2 28c4-14 12-22 26-22"/><path d="M26 2l6 4-6 4" stroke-dasharray="0"/></svg>
                {{ config('salle.nom') }}
            </div>
            <div class="annot a2">
                Pointage par empreinte
                <svg width="46" height="40" viewBox="0 0 46 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-dasharray="3 3"><path d="M4 4c18 0 30 10 34 30"/><path d="M33 31l5 6 4-7" stroke-dasharray="0"/></svg>
            </div>
        </div>
    </section>

    <main class="auth-card">
        <div class="auth-logo"><div class="logo">@include('partials.icone', ['nom' => 'haltere', 'trait' => 2.2])</div>{{ mb_strtoupper(config('salle.nom')) }}</div>
        @yield('content')
    </main>
</div>
@include('partials.alertes', ['flash' => false])
</body>
</html>
