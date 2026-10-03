<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Accueil · {{ config('salle.nom') }}</title>
    @vite(['resources/css/app.css', 'resources/js/accueil.js'])
</head>
<body class="h-full overflow-hidden bg-ink-950 text-white">
<div data-accueil data-url-dernier="{{ route('accueil.dernier') }}" data-url-scan="{{ route('accueil.scan') }}" class="relative flex h-full flex-col">
    <img src="{{ asset('images/connexion.webp') }}" alt="" class="absolute inset-0 size-full object-cover opacity-25">
    <div class="absolute inset-0 bg-gradient-to-b from-ink-950/80 via-ink-950/70 to-ink-950/95"></div>

    <header class="relative flex items-center justify-between px-10 py-7">
        <div class="flex items-center gap-3">
            <x-logo class="h-12 w-12"/>
            <span class="text-3xl font-extrabold tracking-tight">{{ config('salle.nom') }}</span>
        </div>
        <div class="text-right">
            <p id="horloge" class="text-5xl font-bold tabular-nums"></p>
            <p id="date" class="text-sm capitalize text-slate-400"></p>
        </div>
    </header>

    <main class="relative flex flex-1 items-center justify-center px-10">
        {{-- En attente : illustration de la pointeuse --}}
        <div id="attente" class="text-center">
            <div class="relative mx-auto mb-10 flex h-64 w-48 items-center justify-center rounded-[2.2rem] bg-gradient-to-b from-ink-700 to-ink-900 shadow-2xl ring-1 ring-white/15">
                <div class="absolute inset-3 rounded-[1.7rem] bg-ink-950/80 ring-1 ring-brand-400/30"></div>
                <span class="absolute inset-10 animate-pulse rounded-3xl bg-brand-500/10 blur-xl [animation-duration:2.5s]"></span>
                <x-icon name="fingerprint" class="relative size-28 text-brand-400 drop-shadow-[0_0_18px_rgba(45,182,121,0.7)]"/>
                <span class="absolute bottom-6 h-1 w-14 rounded-full bg-brand-400/70"></span>
            </div>
            <p class="text-5xl font-bold tracking-tight">Présentez votre doigt</p>
            <p class="mt-3 text-xl text-slate-300">Accès membre</p>
        </div>

        {{-- Résultat du passage --}}
        <div id="fiche" data-etat="ok" role="status" aria-live="assertive"
             class="hidden w-full max-w-4xl rounded-[2.5rem] p-12 text-center shadow-2xl
                    data-[etat=ok]:bg-gradient-to-br data-[etat=ok]:from-brand-500 data-[etat=ok]:to-brand-700
                    data-[etat=ko]:bg-gradient-to-br data-[etat=ko]:from-red-500 data-[etat=ko]:to-red-700">
            <div class="mx-auto mb-8 flex size-44 items-center justify-center overflow-hidden rounded-full bg-white/15 ring-8 ring-white/25">
                <img id="photo" alt="" class="hidden size-full object-cover">
                <span id="initiale" class="text-7xl font-black"></span>
            </div>
            <p id="message" class="text-6xl font-black tracking-tight"></p>
            <p id="motif" class="mt-3 text-2xl font-semibold text-white/90"></p>
            <p id="nom" class="mt-6 text-5xl font-bold"></p>
            <p id="type" class="mt-2 text-xl uppercase tracking-widest text-white/75"></p>
            <p id="droits" class="mt-8 inline-block rounded-full bg-black/15 px-6 py-2 text-2xl empty:hidden"></p>
        </div>
    </main>

    <footer class="relative flex items-center justify-center gap-6 pb-10">
        <a href="{{ url('/') }}" class="inline-flex items-center gap-2 rounded-xl px-8 py-3.5 text-base font-semibold ring-1 ring-white/40 hover:bg-white/10"><x-icon name="arrow-left" class="size-5"/> Retour</a>
        <a href="{{ route('caisse.index') }}" class="inline-flex items-center gap-2 rounded-xl px-6 py-3.5 text-base font-semibold text-sky-300 hover:bg-white/10"><x-icon name="cash" class="size-5"/> Accès caisse</a>
        <button id="plein-ecran" type="button" class="absolute right-6 rounded-full bg-white/10 px-4 py-2 text-xs text-slate-300 hover:bg-white/20">Plein écran</button>
    </footer>

    <div id="hors-ligne" class="absolute left-1/2 top-6 hidden -translate-x-1/2 rounded-full bg-red-600 px-5 py-2 text-sm font-semibold">
        Connexion au serveur perdue — nouvelle tentative…
    </div>
</div>
</body>
</html>
