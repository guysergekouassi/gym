<!DOCTYPE html>
<html lang="fr" class="h-full bg-white">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Connexion · {{ config('salle.nom') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-white">
<div class="flex min-h-full">
    {{-- Panneau visuel --}}
    <div class="relative hidden flex-1 overflow-hidden bg-ink-950 lg:flex lg:flex-col lg:justify-between lg:p-12">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,_rgba(249,115,22,0.35),_transparent_45%),radial-gradient(circle_at_80%_80%,_rgba(234,88,12,0.25),_transparent_40%)]"></div>
        <div class="absolute -right-24 top-1/3 size-96 rounded-full border-[40px] border-white/5"></div>
        <div class="absolute -left-16 bottom-10 size-64 rounded-full border-[28px] border-brand-500/10"></div>

        <div class="relative flex items-center gap-3">
            <x-logo class="size-12"/>
            <span class="text-2xl font-extrabold text-white">{{ config('salle.nom') }}</span>
        </div>

        <div class="relative max-w-lg">
            <p class="text-5xl font-extrabold leading-tight tracking-tight text-white">
                Chaque séance compte.<br><span class="text-brand-400">Chaque franc aussi.</span>
            </p>
            <p class="mt-6 text-lg text-slate-300">Abonnements, passages, contrôle d'accès par badge et suivi de caisse — au même endroit.</p>
            <div class="mt-10 grid grid-cols-3 gap-4 text-sm">
                <div class="rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
                    <x-icon name="card" class="size-6 text-brand-400"/><p class="mt-2 font-semibold text-white">Badge</p><p class="text-slate-400">Accès contrôlé</p>
                </div>
                <div class="rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
                    <x-icon name="printer" class="size-6 text-brand-400"/><p class="mt-2 font-semibold text-white">Tickets</p><p class="text-slate-400">Reçu thermique</p>
                </div>
                <div class="rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
                    <x-icon name="chart" class="size-6 text-brand-400"/><p class="mt-2 font-semibold text-white">Suivi</p><p class="text-slate-400">Recettes &amp; KPI</p>
                </div>
            </div>
        </div>

        <p class="relative text-xs text-slate-500">{{ config('salle.adresse') }}@if(config('salle.telephone')) · {{ config('salle.telephone') }}@endif</p>
    </div>

    {{-- Formulaire --}}
    <div class="flex flex-1 flex-col justify-center px-6 py-12 sm:px-12 lg:max-w-xl lg:flex-none xl:px-20">
        <div class="mx-auto w-full max-w-sm">
            <div class="flex items-center gap-3 lg:hidden">
                <x-logo/>
                <span class="text-xl font-extrabold">{{ config('salle.nom') }}</span>
            </div>
            <h1 class="mt-8 text-3xl font-bold tracking-tight text-slate-900 lg:mt-0">Bon retour 👋</h1>
            <p class="mt-2 text-sm text-slate-500">Connectez-vous au poste de gestion de la salle.</p>

            <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
                @csrf
                @error('email')
                    <div class="flex items-start gap-2 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-600/20" role="alert">
                        <x-icon name="alert" class="mt-0.5 size-5 shrink-0"/> {{ $message }}
                    </div>
                @enderror

                <div>
                    <label for="email" class="label">Adresse e-mail</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" maxlength="150" class="input py-3">
                </div>
                <div>
                    <label for="password" class="label">Mot de passe</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password" maxlength="200" class="input py-3">
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" class="size-4 rounded border-slate-300 accent-brand-500"> Rester connecté sur ce poste
                </label>
                <button type="submit" class="btn-primary btn-lg w-full">Se connecter</button>
            </form>

            <p class="mt-10 flex items-center gap-2 text-xs text-slate-400">
                <x-icon name="shield" class="size-4"/> Accès réservé au personnel. Les tentatives de connexion sont journalisées.
            </p>
        </div>
    </div>
</div>
</body>
</html>
