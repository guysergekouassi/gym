@extends('layouts.app')
@section('title', 'Paramètres')

@php
    use App\Support\Horaires;
    $heures = Horaires::heures();
@endphp

@section('content')
<div class="mb-6">
    <h1 class="text-3xl font-bold tracking-tight text-slate-900">Paramètres</h1>
    <p class="mt-1 text-slate-500">Nom de l'entreprise, coordonnées et horaires des séances imprimés sur les tickets de caisse.</p>
</div>

<form method="POST" action="{{ route('admin.parametres.update') }}" class="grid gap-6 xl:grid-cols-3">
    @csrf @method('PUT')
    <div class="space-y-6 xl:col-span-2">
    <div class="card">
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
    </div>

    {{-- Horaires des séances : un jour = non défini, fermé, ou ouvert de … à … --}}
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Horaires des séances</h2>
                <p class="mt-0.5 text-sm text-slate-500">Imprimés sur les tickets. « Non défini » ou ✕ retire le jour.</p>
            </div>
        </div>
        <div class="card-body divide-y divide-slate-100 py-2">
            @foreach(Horaires::JOURS as $n => $nom)
                @php
                    $etat = old("horaires.$n.etat", $horaires[$n]['etat'] ?? '');
                    $debut = old("horaires.$n.debut", $horaires[$n]['debut'] ?? '06:00');
                    $fin = old("horaires.$n.fin", $horaires[$n]['fin'] ?? '21:00');
                @endphp
                <div data-horaire class="flex flex-wrap items-center gap-3 py-3">
                    <span class="w-24 font-semibold text-slate-900">{{ $nom }}</span>
                    <select name="horaires[{{ $n }}][etat]" data-horaire-etat class="input w-36 py-2" aria-label="{{ $nom }} : ouvert ou fermé">
                        <option value="">Non défini</option>
                        <option value="{{ Horaires::OUVERT }}" @selected($etat === Horaires::OUVERT)>Ouvert</option>
                        <option value="{{ Horaires::FERME }}" @selected($etat === Horaires::FERME)>Fermé</option>
                    </select>
                    <div data-horaire-heures class="flex items-center gap-2 {{ $etat === Horaires::OUVERT ? '' : 'hidden' }}">
                        <select name="horaires[{{ $n }}][debut]" class="input w-28 py-2 font-mono" aria-label="{{ $nom }} : début">
                            @foreach($heures as $h)<option value="{{ $h }}" @selected($debut === $h)>{{ $h }}</option>@endforeach
                        </select>
                        <span class="text-slate-400">à</span>
                        <select name="horaires[{{ $n }}][fin]" class="input w-28 py-2 font-mono" aria-label="{{ $nom }} : fin">
                            @foreach($heures as $h)<option value="{{ $h }}" @selected($fin === $h)>{{ $h }}</option>@endforeach
                        </select>
                    </div>
                    <span data-horaire-ferme class="pill-gray {{ $etat === Horaires::FERME ? '' : 'hidden' }}">Pas de séance</span>
                    <button type="button" data-horaire-effacer class="ml-auto rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600 {{ $etat === '' ? 'invisible' : '' }}"
                            title="Supprimer l'horaire de {{ mb_strtolower($nom) }}" aria-label="Supprimer l'horaire de {{ mb_strtolower($nom) }}"><x-icon name="x" class="size-4"/></button>
                </div>
            @endforeach
        </div>
    </div>

    <div class="flex justify-end">
        <button type="submit" class="btn-primary"><x-icon name="check" class="size-4"/> Enregistrer</button>
    </div>
    </div>

    {{-- Aperçu du ticket, mis à jour pendant la saisie --}}
    <div class="card h-fit p-5 xl:sticky xl:top-24">
        <p class="mb-3 text-sm font-semibold text-slate-900">Aperçu du ticket</p>
        <div class="mx-auto w-64 bg-white p-4 font-mono text-xs text-black shadow-md ring-1 ring-slate-200">
            <p class="text-center text-sm font-bold" data-apercu-cible="nom">{{ $salle['nom'] }}</p>
            <p class="text-center" data-apercu-cible="adresse">{{ $salle['adresse'] }}</p>
            <p class="text-center">Tél : <span data-apercu-cible="telephone">{{ $salle['telephone'] }}</span></p>
            <p class="text-center" data-apercu-cible="email">{{ $salle['email'] }}</p>
            <p class="mt-1 text-center">Arrivée : {{ now()->format('H:i:s') }}</p>
            @if($seance = Horaires::duJour(today()))<p class="text-center">Séance du jour : {{ $seance }}</p>@endif
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
