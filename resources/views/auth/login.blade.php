<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <x-icones-app/>
    <title>Connexion · {{ config('salle.nom') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-[#071426]">
<div class="relative min-h-full overflow-hidden">
    {{-- Visuel (le slogan fait partie de l'image) --}}
    <img src="{{ asset('images/connexion.webp') }}" alt=""
         class="absolute inset-0 size-full object-cover object-[55%_center] lg:object-left">
    <div class="absolute inset-0 bg-gradient-to-t from-[#071426] via-[#071426]/70 to-transparent lg:hidden"></div>

    <h1 class="sr-only">{{ config('salle.nom') }} — Votre progression, notre priorité !</h1>

    <div class="relative flex min-h-screen items-end justify-center px-6 pb-10 lg:items-center lg:justify-end lg:px-[4.5vw] lg:pb-0">
        <div class="w-full max-w-md lg:w-[30vw] lg:max-w-[500px]">
            <img src="{{ asset('images/logo-epikaizo.png') }}" alt="{{ config('salle.nom') }}" class="mb-8" style="height: 7rem; width: auto">
            {{-- Sur mobile, le slogan de l'image est hors cadre : on le reprend en texte --}}
            <p class="mb-8 text-3xl font-extrabold leading-tight text-white lg:hidden" aria-hidden="true">
                Votre progression,<br><span class="text-emerald-400">notre priorité !</span>
            </p>
            <h2 class="text-4xl font-extrabold tracking-tight text-white lg:text-[2.7vw] xl:text-5xl">Connexion</h2>
            <p class="mt-2 text-lg text-slate-300">Connectez-vous au poste de gestion</p>

            <form method="POST" action="{{ route('login') }}" class="mt-10 space-y-6">
                @csrf
                @error('email')
                    <div class="flex items-start gap-2 rounded-lg bg-red-500/15 px-4 py-3 text-sm text-red-200 ring-1 ring-red-400/40" role="alert">
                        <x-icon name="alert" class="mt-0.5 size-5 shrink-0"/> {{ $message }}
                    </div>
                @enderror

                <div>
                    <label for="email" class="mb-2.5 block text-base font-semibold text-white">E-mail</label>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-4 top-1/2 z-10 size-6 -translate-y-1/2 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/>
                        </svg>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" maxlength="150"
                               placeholder="votre@email.com"
                               class="block w-full rounded-lg border border-white/40 bg-[#0a1a2e]/60 py-4 pl-14 pr-4 text-base text-white placeholder:text-slate-400 backdrop-blur-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/40">
                    </div>
                </div>

                <div>
                    <label for="password" class="mb-2.5 block text-base font-semibold text-white">Mot de passe</label>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-4 top-1/2 z-10 size-6 -translate-y-1/2 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                        </svg>
                        <input id="password" type="password" name="password" required autocomplete="current-password" maxlength="200"
                               placeholder="Votre mot de passe"
                               class="block w-full rounded-lg border border-white/40 bg-[#0a1a2e]/60 py-4 pl-14 pr-14 text-base text-white placeholder:text-slate-400 backdrop-blur-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/40">
                        <button type="button" data-afficher-mdp="password" aria-label="Afficher le mot de passe"
                                class="absolute right-3 top-1/2 z-10 -translate-y-1/2 rounded-md p-1.5 text-white hover:text-emerald-300">
                            <svg data-oeil-ferme class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/>
                            </svg>
                            <svg data-oeil-ouvert class="hidden size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <label class="flex items-center gap-3 text-base text-white">
                    <input type="checkbox" name="remember" class="size-5 rounded border-white/50 bg-transparent accent-emerald-500">
                    Rester connecté
                </label>

                <button type="submit" class="w-full cursor-pointer rounded-lg bg-[#16a36a] py-4 text-lg font-semibold text-white shadow-lg shadow-emerald-900/40 transition hover:bg-[#13915e] disabled:opacity-70">
                    Se connecter
                </button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
