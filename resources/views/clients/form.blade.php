@extends('layouts.app')
@section('title', $client->exists ? 'Modifier le client' : 'Nouveau client')

@php
    use App\Models\Client;
@endphp

@section('content')
<x-page-header :title="$client->exists ? 'Modifier '.$client->nom_complet : 'Nouveau client'"
               subtitle="Les champs marqués * sont obligatoires. Le n° de pointeuse peut être attribué plus tard."/>

<form method="POST" enctype="multipart/form-data"
      action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}"
      class="grid gap-6 lg:grid-cols-3">
    @csrf
    @if($client->exists) @method('PUT') @endif
    @if(request('apres') === 'abonner' || old('apres') === 'abonner')
        <input type="hidden" name="apres" value="abonner">
    @endif

    <div class="card lg:col-span-2">
        <div class="card-header"><h2 class="card-title">Identité</h2></div>
        <div class="card-body grid gap-5 sm:grid-cols-2">
            <div>
                <label for="nom" class="label">Nom *</label>
                <input id="nom" type="text" name="nom" required maxlength="100" value="{{ old('nom', $client->nom) }}" class="input">
            </div>
            <div>
                <label for="prenoms" class="label">Prénoms</label>
                <input id="prenoms" type="text" name="prenoms" maxlength="150" value="{{ old('prenoms', $client->prenoms) }}" class="input">
            </div>
            <div>
                <label for="telephone" class="label">Téléphone</label>
                <input id="telephone" type="tel" name="telephone" maxlength="20" value="{{ old('telephone', $client->telephone) }}" class="input" placeholder="07 00 00 00 00">
            </div>
            <div>
                <label for="email" class="label">E-mail</label>
                <input id="email" type="email" name="email" maxlength="150" value="{{ old('email', $client->email) }}" class="input">
            </div>
            <div>
                <label for="date_adhesion" class="label">Date d'adhésion</label>
                <input id="date_adhesion" type="date" name="date_adhesion" value="{{ old('date_adhesion', ($client->date_adhesion ?? today())->toDateString()) }}" max="{{ today()->addYear()->toDateString() }}" class="input">
            </div>
            <div>
                <label for="sexe" class="label">Sexe</label>
                <select id="sexe" name="sexe" class="input">
                    <option value="">—</option>
                    <option value="M" @selected(old('sexe', $client->sexe) === 'M')>Homme</option>
                    <option value="F" @selected(old('sexe', $client->sexe) === 'F')>Femme</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label for="notes" class="label">Notes</label>
                <textarea id="notes" name="notes" rows="3" maxlength="1000" class="input" placeholder="Objectif, contre-indication médicale, remarque…">{{ old('notes', $client->notes) }}</textarea>
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Accès à la salle</h2></div>
            <div class="card-body space-y-5">
                <fieldset>
                    <legend class="label">Type de client *</legend>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach(Client::TYPES as $valeur => $libelle)
                            <label class="choice items-center text-center text-sm font-semibold">
                                <input type="radio" name="type" value="{{ $valeur }}" required
                                       @checked(old('type', $client->type) === $valeur)
                                       data-toggle-abonnement="{{ $valeur === Client::TYPE_ABONNE ? '1' : '0' }}">
                                {{ $libelle }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div>
                    <label for="empreinte_id" class="label">N° sur la pointeuse</label>
                    <input id="empreinte_id" data-no-enter type="text" name="empreinte_id" maxlength="9" inputmode="numeric" value="{{ old('empreinte_id', $client->empreinte_id) }}" class="input font-mono" placeholder="ex. {{ $numeroSuggere }}">
                    @if(! $client->empreinte_id)
                        <button type="button" class="mt-2 btn-light btn-sm" data-remplir="empreinte_id" data-valeur="{{ $numeroSuggere }}">Attribuer le n° {{ $numeroSuggere }}</button>
                    @endif
                    <p class="hint">Ce n° sera envoyé à la pointeuse avec le nom du client. Enregistrez ensuite son doigt sur la pointeuse (Menu → Utilisateurs → ce n° → Empreinte).</p>
                </div>
            </div>
        </div>

        {{-- Bloc abonnement : visible uniquement pour les abonnés, caché pour les journaliers --}}
        @if(! $client->exists)
        <div id="bloc-abonnement" class="card" style="display: {{ old('type', $client->type) === Client::TYPE_ABONNE ? 'block' : 'none' }}">
            <div class="card-header"><h2 class="card-title">Abonnement immédiat <span class="text-sm font-normal opacity-60">(optionnel)</span></h2></div>
            <div class="card-body space-y-5">
                <div>
                    <label for="formule_id" class="label">Formule</label>
                    <select id="formule_id" name="formule_id" class="input">
                        <option value="">— Choisir une formule —</option>
                        @foreach($formules as $formule)
                            <option value="{{ $formule->id }}"
                                    data-prix="{{ number_format($formule->prix, 0, ',', ' ') }}"
                                    data-resume="{{ $formule->resume() }}"
                                    @selected(old('formule_id') == $formule->id)>
                                {{ $formule->nom }} — {{ number_format($formule->prix, 0, ',', ' ') }} FCFA ({{ $formule->resume() }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div id="bloc-paiement-abonnement" style="display: {{ old('formule_id') ? 'block' : 'none' }}" class="space-y-4">
                    <div>
                        <label for="reference" class="label">Référence / N° transaction <span class="opacity-50">(optionnel)</span></label>
                        <input id="reference" type="text" name="reference" maxlength="100"
                               value="{{ old('reference') }}" class="input" placeholder="ex. Wave #123456">
                    </div>
                    <div id="apercu-formule" class="rounded-lg bg-primary/10 border border-primary/20 p-4 text-sm space-y-1 hidden">
                        <div class="font-semibold text-primary" id="apercu-nom"></div>
                        <div class="opacity-70" id="apercu-detail"></div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div class="card">
            <div class="card-header"><h2 class="card-title">Photo</h2></div>
            <div class="card-body">
                <x-champ-photo id="photo" :actuelle="$client->photo_url"/>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="btn-primary flex-1"><x-icon name="check" class="size-4"/> Enregistrer</button>
            <a href="{{ $client->exists ? route('clients.show', $client) : route('clients.index') }}" class="btn-light">Annuler</a>
        </div>
    </div>
</form>

@push('scripts')
<script>
(function () {
    const blocAbonnement  = document.getElementById('bloc-abonnement');
    const blocPaiement    = document.getElementById('bloc-paiement-abonnement');
    const selectFormule   = document.getElementById('formule_id');
    const apercuBox       = document.getElementById('apercu-formule');
    const apercuNom       = document.getElementById('apercu-nom');
    const apercuDetail    = document.getElementById('apercu-detail');

    // Afficher / masquer le bloc abonnement selon le type choisi
    document.querySelectorAll('input[name="type"]').forEach(radio => {
        radio.addEventListener('change', () => {
            const estAbonne = radio.dataset.toggleAbonnement === '1' && radio.checked;
            if (blocAbonnement) blocAbonnement.style.display = estAbonne ? 'block' : 'none';
            if (!estAbonne && selectFormule) selectFormule.value = '';
            if (!estAbonne && blocPaiement) blocPaiement.style.display = 'none';
        });
    });

    // Afficher le bloc paiement dès qu'une formule est choisie
    if (selectFormule) {
        selectFormule.addEventListener('change', () => {
            const opt = selectFormule.selectedOptions[0];
            if (opt && opt.value) {
                blocPaiement.style.display = 'block';
                apercuNom.textContent    = opt.text.split('—')[0].trim();
                apercuDetail.textContent = opt.dataset.resume + ' · ' + opt.dataset.prix + ' FCFA';
                apercuBox.classList.remove('hidden');
            } else {
                blocPaiement.style.display = 'none';
                apercuBox.classList.add('hidden');
            }
        });
        // Déclencher au chargement si old('formule_id') est présent
        if (selectFormule.value) selectFormule.dispatchEvent(new Event('change'));
    }
})();
</script>
@endpush
@endsection
