@extends('layouts.app')
@section('title', 'Mot de passe')

@section('content')
<x-page-header title="Mon mot de passe" :subtitle="$force ? 'Première connexion : choisissez votre mot de passe personnel.' : 'Modifiez le mot de passe de votre compte.'"/>

<div class="grid gap-6 lg:grid-cols-3">
    <form method="POST" action="{{ route('mot-de-passe.update') }}" class="card lg:col-span-2">
        @csrf @method('PUT')
        <div class="card-body space-y-5">
            <div>
                <label for="current_password" class="label">Mot de passe actuel</label>
                <input id="current_password" type="password" name="current_password" required autocomplete="current-password" class="input">
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="password" class="label">Nouveau mot de passe</label>
                    <input id="password" type="password" name="password" required minlength="10" autocomplete="new-password" class="input">
                </div>
                <div>
                    <label for="password_confirmation" class="label">Confirmation</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required minlength="10" autocomplete="new-password" class="input">
                </div>
            </div>
        </div>
        <div class="flex justify-end border-t border-slate-100 px-5 py-4">
            <button type="submit" class="btn-primary"><x-icon name="key" class="size-4"/> Enregistrer</button>
        </div>
    </form>

    <div class="card card-body h-fit">
        <h2 class="card-title flex items-center gap-2"><x-icon name="shield" class="size-5 text-brand-500"/> Règles</h2>
        <ul class="mt-3 space-y-2 text-sm text-slate-600">
            <li>• 10 caractères minimum</li>
            <li>• Au moins une majuscule et une minuscule</li>
            <li>• Au moins un chiffre</li>
            <li>• Ne le partagez avec personne, pas même un collègue</li>
        </ul>
        <p class="mt-4 text-xs text-slate-500">Après le changement, vos autres sessions ouvertes sont automatiquement déconnectées.</p>
    </div>
</div>
@endsection
