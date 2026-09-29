@extends('layouts.app')
@section('title', $client->exists ? 'Modifier le client' : 'Nouveau client')

@php
    use App\Models\Client;
@endphp

@section('content')
<div class="top">
    <div>
        <div class="eyebrow"><a href="{{ $client->exists ? route('clients.show', $client) : route('clients.index') }}">← {{ $client->exists ? $client->nom_complet : 'Clients' }}</a></div>
        <h1>{{ $client->exists ? 'Modifier le client' : 'Nouveau client' }}</h1>
    </div>
</div>

<form method="POST" enctype="multipart/form-data"
      action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}"
      class="card form-card">
    @csrf
    @if($client->exists) @method('PUT') @endif

    <div class="grid g2">
        <label class="fld" for="type">Type
            <select id="type" name="type" required>
                @foreach(Client::TYPES as $valeur => $libelle)
                    <option value="{{ $valeur }}" @selected(old('type', $client->type) === $valeur)>{{ $libelle }}</option>
                @endforeach
            </select>
        </label>
        <label class="fld" for="sexe">Sexe
            <select id="sexe" name="sexe">
                <option value="">—</option>
                <option value="M" @selected(old('sexe', $client->sexe) === 'M')>Homme</option>
                <option value="F" @selected(old('sexe', $client->sexe) === 'F')>Femme</option>
            </select>
        </label>

        <label class="fld" for="nom">Nom *
            <input id="nom" type="text" name="nom" required value="{{ old('nom', $client->nom) }}">
        </label>
        <label class="fld" for="prenoms">Prénoms
            <input id="prenoms" type="text" name="prenoms" value="{{ old('prenoms', $client->prenoms) }}">
        </label>

        <label class="fld" for="telephone">Téléphone
            <input id="telephone" type="tel" name="telephone" value="{{ old('telephone', $client->telephone) }}">
        </label>
        <label class="fld" for="email">E-mail
            <input id="email" type="email" name="email" value="{{ old('email', $client->email) }}">
        </label>

        <label class="fld" for="date_naissance">Date de naissance
            <input id="date_naissance" type="date" name="date_naissance" max="{{ today()->subDay()->toDateString() }}" value="{{ old('date_naissance', $client->date_naissance?->toDateString()) }}">
        </label>
        <div class="fld">
            <label for="empreinte_id">N° d'empreinte</label>
            <div class="champ-capture">
                <input id="empreinte_id" type="text" name="empreinte_id" value="{{ old('empreinte_id', $client->empreinte_id) }}" placeholder="ex. 125">
                <button type="button" class="btn ghost sm" data-capturer="empreinte" data-cible="empreinte_id">@include('partials.icone', ['nom' => 'empreinte', 'taille' => 16])Capturer</button>
            </div>
            <span class="aide">Enrôlez le doigt sur le lecteur, cliquez sur « Capturer » puis faites poser le doigt une fois.</span>
        </div>
        <div class="fld">
            <label for="carte_id">N° de carte ou badge</label>
            <div class="champ-capture">
                <input id="carte_id" type="text" name="carte_id" value="{{ old('carte_id', $client->carte_id) }}" placeholder="Facultatif">
                <button type="button" class="btn ghost sm" data-capturer="carte" data-cible="carte_id">@include('partials.icone', ['nom' => 'carte', 'taille' => 16])Capturer</button>
            </div>
            <span class="aide">Ou cliquez dans le champ et passez la carte sur le lecteur USB. Sans carte, le QR code de l'espace membre suffit{{ $client->code_acces ? ' (code '.$client->code_acces.')' : '' }}.</span>
        </div>
        <label class="fld" for="parrain_telephone">Parrain (téléphone du membre qui l'a recommandé)
            <input id="parrain_telephone" type="tel" name="parrain_telephone" value="{{ old('parrain_telephone', $client->parrain?->telephone) }}" placeholder="Facultatif">
        </label>
    </div>

    <label class="fld" for="photo">Photo
        <input id="photo" type="file" name="photo" accept="image/*" style="padding:10px 14px">
    </label>
    @if($client->photo_url)
        <img src="{{ $client->photo_url }}" alt="Photo actuelle" style="width:80px;height:80px;object-fit:cover;border-radius:12px">
    @endif

    <label class="fld" for="notes">Notes
        <textarea id="notes" name="notes" rows="3">{{ old('notes', $client->notes) }}</textarea>
    </label>

    <div class="actions">
        <button class="btn">Enregistrer</button>
        <a href="{{ $client->exists ? route('clients.show', $client) : route('clients.index') }}" class="btn ghost">Annuler</a>
    </div>
</form>
@endsection

@push('scripts')
<script data-modal-script>
(() => {
    const URL_CAPTURE = @json(route('clients.capture'));
    document.querySelectorAll('[data-capturer]').forEach((bouton) => bouton.addEventListener('click', () => {
        const type = bouton.dataset.capturer;
        const champ = document.getElementById(bouton.dataset.cible);
        const depuis = Math.floor(Date.now() / 1000);
        let minuteur = null, fin = null;

        // La carte peut aussi être lue par un lecteur USB branché sur ce poste : il « tape » le numéro puis Entrée
        const clavier = type === 'carte' ? (e) => {
            const input = Swal.getPopup()?.querySelector('#capture-usb');
            if (e.key === 'Enter' && input?.value.trim()) { e.preventDefault(); terminer(input.value.trim()); }
        } : null;

        const terminer = (identifiant) => {
            clearInterval(minuteur); clearTimeout(fin);
            champ.value = identifiant;
            champ.dispatchEvent(new Event('input', { bubbles: true }));
            Swal.close();
            window.gfToast(type === 'carte' ? `Carte ${identifiant} enregistrée dans la fiche` : `Empreinte n° ${identifiant} enregistrée dans la fiche`);
        };

        window.gfAlerte.fire({
            icon: 'info',
            title: type === 'carte' ? 'Passez la carte' : 'Posez le doigt sur le lecteur',
            html: (type === 'carte'
                ? 'Passez la carte sur le lecteur de l’entrée, ou sur le lecteur USB de ce poste.<input id="capture-usb" class="swal2-input" autocomplete="off" placeholder="N° lu par le lecteur USB">'
                : 'Le doigt doit déjà être enrôlé sur l’appareil. Faites-le poser une fois : le numéro arrive ici tout seul.')
                + '<p style="margin:14px 0 0;font-size:13px">En attente du lecteur…</p>',
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: 'Annuler',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
                Swal.getPopup().querySelector('#capture-usb')?.focus();
                if (clavier) document.addEventListener('keydown', clavier, true);
            },
            willClose: () => { clearInterval(minuteur); clearTimeout(fin); if (clavier) document.removeEventListener('keydown', clavier, true); },
        });

        minuteur = setInterval(async () => {
            try {
                const r = await fetch(`${URL_CAPTURE}?type=${type}&depuis=${depuis}`, { headers: { Accept: 'application/json' } });
                const d = await r.json();
                if (d.identifiant) terminer(d.identifiant);
                else if (d.deja_attribue) {
                    clearInterval(minuteur);
                    window.gfAlerte.fire({ icon: 'error', title: 'Déjà attribué', text: `Ce${type === 'carte' ? 'tte carte' : 't empreinte'} appartient déjà à un autre client.`, confirmButtonText: 'Compris' });
                }
            } catch { /* nouvel essai au prochain tour */ }
        }, 1500);

        fin = setTimeout(() => {
            clearInterval(minuteur);
            window.gfAlerte.fire({ icon: 'warning', title: 'Rien reçu du lecteur', text: 'Vérifiez que le lecteur est allumé et relié à GymFlow, puis réessayez. Vous pouvez aussi saisir le numéro à la main.', confirmButtonText: 'Compris' });
        }, 90000);
    }));
})();
</script>
@endpush