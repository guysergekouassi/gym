<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion · {{ config('salle.nom') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center p-4">
<form method="POST" action="{{ route('login') }}" class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-8 space-y-4">
    @csrf
    <h1 class="text-2xl font-bold text-center">{{ config('salle.nom') }}</h1>
    <p class="text-center text-slate-500 text-sm">Connexion au poste de gestion</p>

    @error('email')
        <div class="rounded bg-red-100 text-red-800 px-3 py-2 text-sm">{{ $message }}</div>
    @enderror

    <label class="block">
        <span class="text-sm font-medium">E-mail</span>
        <input type="email" name="email" value="{{ old('email') }}" required autofocus
               class="mt-1 w-full rounded-lg border-slate-300 border px-3 py-2">
    </label>
    <label class="block">
        <span class="text-sm font-medium">Mot de passe</span>
        <input type="password" name="password" required class="mt-1 w-full rounded-lg border-slate-300 border px-3 py-2">
    </label>
    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="remember"> Rester connecté
    </label>
    <button class="w-full rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5">Se connecter</button>
</form>
</body>
</html>
