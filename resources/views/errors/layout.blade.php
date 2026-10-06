<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <x-icones-app/>
    <title>@yield('code') · {{ config('salle.nom') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex h-full items-center justify-center bg-slate-100 p-6">
<div class="card max-w-md p-10 text-center">
    <x-logo class="mx-auto size-14"/>
    <p class="mt-6 text-6xl font-black text-brand-500">@yield('code')</p>
    <h1 class="mt-2 text-xl font-bold text-slate-900">@yield('titre')</h1>
    <p class="mt-2 text-sm text-slate-500">@yield('message')</p>
    <a href="{{ url('/') }}" class="btn-primary mt-8">Revenir à l'accueil</a>
</div>
</body>
</html>
