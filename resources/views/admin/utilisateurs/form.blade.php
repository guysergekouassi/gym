@extends('layouts.app')
@section('title', $utilisateur->exists ? 'Modifier un compte' : 'Nouveau compte')

@php use App\Models\User; @endphp

@section('content')
<x-page-header :title="$utilisateur->exists ? 'Modifier '.$utilisateur->name : 'Nouveau compte'"
               subtitle="Le mot de passe saisi ici est provisoire : la personne devra le changer à sa première connexion."/>

<form method="POST" action="{{ $utilisateur->exists ? route('admin.utilisateurs.update', $utilisateur) : route('admin.utilisateurs.store') }}" class="card max-w-3xl">
    @csrf
    @if($utilisateur->exists) @method('PUT') @endif
    <div class="card-body grid gap-5 sm:grid-cols-2">
        <div>
            <label for="name" class="label">Nom affiché *</label>
            <input id="name" name="name" required maxlength="100" value="{{ old('name', $utilisateur->name) }}" class="input">
        </div>
        <div>
            <label for="email" class="label">E-mail de connexion *</label>
            <input id="email" type="email" name="email" required maxlength="150" autocomplete="off" value="{{ old('email', $utilisateur->email) }}" class="input">
        </div>
        <fieldset class="sm:col-span-2">
            <legend class="label">Rôle *</legend>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="choice">
                    <input type="radio" name="role" value="{{ User::ROLE_CAISSIER }}" @checked(old('role', $utilisateur->role) === User::ROLE_CAISSIER)>
                    <span class="font-semibold">Caissière</span>
                    <span class="text-xs text-slate-500">Encaisse les passages et abonnements, gère les fiches clients. Ne voit que sa propre caisse.</span>
                </label>
                <label class="choice">
                    <input type="radio" name="role" value="{{ User::ROLE_ADMIN }}" @checked(old('role', $utilisateur->role) === User::ROLE_ADMIN)>
                    <span class="font-semibold">Responsable (admin)</span>
                    <span class="text-xs text-slate-500">Tout : tableau de bord, encaissements, annulations, tarifs, comptes.</span>
                </label>
            </div>
        </fieldset>

        <div class="sm:col-span-2 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200">
            <p class="mb-3 flex items-center gap-2 text-sm font-semibold"><x-icon name="key" class="size-4"/>
                {{ $utilisateur->exists ? 'Réinitialiser le mot de passe (laisser vide pour ne pas changer)' : 'Mot de passe provisoire *' }}</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <input type="password" name="password" autocomplete="new-password" minlength="10" maxlength="200" @required(! $utilisateur->exists) class="input" placeholder="Mot de passe">
                <input type="password" name="password_confirmation" autocomplete="new-password" minlength="10" maxlength="200" @required(! $utilisateur->exists) class="input" placeholder="Confirmation">
            </div>
            <p class="hint">10 caractères minimum avec majuscule, minuscule et chiffre. Une réinitialisation déconnecte la personne de tous ses postes.</p>
        </div>

        @if($utilisateur->exists)
            <label class="flex items-center gap-2 text-sm sm:col-span-2">
                <input type="checkbox" name="actif" value="1" @checked(old('actif', $utilisateur->actif)) class="size-4 accent-brand-500">
                Compte actif <span class="text-slate-500">(décocher bloque immédiatement l'accès, sans rien supprimer)</span>
            </label>
        @endif
    </div>
    <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4">
        <a href="{{ route('admin.utilisateurs.index') }}" class="btn-light">Annuler</a>
        <button type="submit" class="btn-primary"><x-icon name="check" class="size-4"/> Enregistrer</button>
    </div>
</form>
@endsection
