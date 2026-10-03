@extends('layouts.app')
@section('title', 'Paramètres')

@section('content')
<div class="mb-6">
    <h1 class="text-3xl font-bold tracking-tight text-slate-900">Paramètres</h1>
    <p class="mt-1 text-slate-500">Nom de l'entreprise et coordonnées imprimées sur les tickets de caisse.</p>
</div>

<form method="POST" action="{{ route('admin.parametres.update') }}" class="grid gap-6 xl:grid-cols-3">
    @csrf @method('PUT')
    <div class="card xl:col-span-2">
        <div class="card-header"><h2 class="card-title">Entreprise</h2></div>
        <div class="card-body grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="nom" class="label">Nom de l'entreprise *</label>
                <input id="nom" name="nom" required maxlength="60" value="{{ old('nom', $salle['nom']) }}" class="input text-base font-semibold" data-apercu="nom">
                <p class="hint">Affiché dans le menu, sur la page de connexion, l'écran d'accueil et les tickets.</p>
            </div>
            <div class="sm:col-span-2">
                <label for="adresse" class="label">Adresse</label>
                <input id="adresse" name="adresse" maxlength="150" value="{{ old('adresse', $salle['adresse']) }}" class="input" placeholder="ex. Cocody Angré, Abidjan" data-apercu="adresse">
            </div>
            <div>
                <label for="telephone" class="label">Téléphone</label>
                <input id="telephone" name="telephone" maxlength="40" value="{{ old('telephone', $salle['telephone']) }}" class="input" placeholder="07 00 00 00 00" data-apercu="telephone">
            </div>
            <div>
                <label for="email" class="label">E-mail</label>
                <input id="email" type="email" name="email" maxlength="150" value="{{ old('email', $salle['email']) }}" class="input" placeholder="contact@masalle.ci" data-apercu="email">
            </div>
            <div class="sm:col-span-2">
                <label for="message_recu" class="label">Message en bas du ticket</label>
                <input id="message_recu" name="message_recu" maxlength="120" value="{{ old('message_recu', $salle['message_recu']) }}" class="input" placeholder="Merci et bonne séance !" data-apercu="message_recu">
            </div>
        </div>
        <div class="flex justify-end border-t border-slate-100 px-5 py-4">
            <button type="submit" class="btn-primary"><x-icon name="check" class="size-4"/> Enregistrer</button>
        </div>
    </div>

    {{-- Aperçu du ticket, mis à jour pendant la saisie --}}
    <div class="card h-fit p-5">
        <p class="mb-3 text-sm font-semibold text-slate-900">Aperçu du ticket</p>
        <div class="mx-auto w-64 bg-white p-4 font-mono text-xs text-black shadow-md ring-1 ring-slate-200">
            <p class="text-center text-sm font-bold" data-apercu-cible="nom">{{ $salle['nom'] }}</p>
            <p class="text-center" data-apercu-cible="adresse">{{ $salle['adresse'] }}</p>
            <p class="text-center">Tél : <span data-apercu-cible="telephone">{{ $salle['telephone'] }}</span></p>
            <p class="text-center" data-apercu-cible="email">{{ $salle['email'] }}</p>
            <p class="my-2 border-t border-dashed border-black"></p>
            <p>Ticket   R20261005-000042</p>
            <p>Objet    Abonnement Mensuel</p>
            <p class="my-2 text-center text-base font-bold">15 000 FCFA</p>
            <p class="my-2 border-t border-dashed border-black"></p>
            <p class="text-center" data-apercu-cible="message_recu">{{ $salle['message_recu'] }}</p>
        </div>
    </div>
</form>
@endsection
