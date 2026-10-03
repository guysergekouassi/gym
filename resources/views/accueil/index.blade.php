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
<div data-accueil data-url-dernier="{{ route('accueil.dernier') }}" data-url-scan="{{ route('accueil.scan') }}"
     class="relative flex h-full flex-col bg-[radial-gradient(ellipse_at_top,_rgba(249,115,22,0.18),_transparent_60%)]">

    <header class="flex items-center justify-between px-10 py-6">
        <div class="flex items-center gap-4">
            <x-logo class="size-14"/>
            <div>
                <p class="text-3xl font-extrabold tracking-tight">{{ config('salle.nom') }}</p>
                <p class="text-sm text-slate-400">{{ config('salle.adresse') }}</p>
            </div>
        </div>
        <div class="text-right">
            <p id="horloge" class="text-5xl font-bold tabular-nums"></p>
            <p id="date" class="text-sm capitalize text-slate-400"></p>
        </div>
    </header>

    <main class="flex flex-1 items-center justify-center px-10 pb-10">
        <div id="attente" class="text-center">
            <div class="relative mx-auto mb-10 flex size-56 items-center justify-center">
                <span class="absolute inset-0 animate-ping rounded-full bg-brand-500/20 [animation-duration:2.5s]"></span>
                <span class="absolute inset-6 rounded-full bg-brand-500/10 ring-1 ring-brand-400/30"></span>
                <x-icon name="card" class="relative size-24 text-brand-400"/>
            </div>
            <p class="text-5xl font-bold tracking-tight">Passez votre badge</p>
            <p class="mt-4 text-xl text-slate-400">sur le lecteur pour entrer</p>
        </div>

        <div id="fiche" data-etat="ok" role="status" aria-live="assertive"
             class="group hidden w-full max-w-4xl rounded-[2.5rem] p-12 text-center shadow-2xl
                    data-[etat=ok]:bg-gradient-to-br data-[etat=ok]:from-emerald-500 data-[etat=ok]:to-emerald-700 data-[etat=ok]:shadow-emerald-900/50
                    data-[etat=ko]:bg-gradient-to-br data-[etat=ko]:from-red-500 data-[etat=ko]:to-red-700 data-[etat=ko]:shadow-red-900/50">
            <div class="mx-auto mb-8 flex size-48 items-center justify-center overflow-hidden rounded-full bg-white/15 ring-8 ring-white/25">
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

    <div id="hors-ligne" class="hidden absolute bottom-6 left-1/2 -translate-x-1/2 rounded-full bg-red-600 px-5 py-2 text-sm font-semibold">
        Connexion au serveur perdue — nouvelle tentative…
    </div>
    <button id="plein-ecran" type="button" class="absolute bottom-6 right-6 rounded-full bg-white/10 px-4 py-2 text-xs text-slate-300 hover:bg-white/20">
        Plein écran
    </button>
</div>
</body>
</html>
