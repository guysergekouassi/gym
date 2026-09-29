@extends('layouts.app')
@section('title', 'Nouvelle campagne')

@section('content')
<div class="top"><div><div class="eyebrow"><a href="{{ route('admin.campagnes.index') }}">← Campagnes</a></div><h1>Nouvelle campagne</h1></div></div>

<form method="POST" action="{{ route('admin.campagnes.store') }}" class="cash" data-campagne data-confirmer="Lancer la campagne ?" data-texte="Un message va être préparé pour chaque destinataire du groupe choisi ({texte} personne(s))." data-texte-depuis="[data-nb]" data-bouton="Lancer">
    @csrf
    <section class="card">
        <label class="fld" for="nom">Nom interne *<input id="nom" name="nom" value="{{ old('nom') }}" required maxlength="100" placeholder="ex. Promo rentrée septembre"></label>
        <fieldset class="grid" style="border:0;padding:0;margin:0;gap:8px">
            <legend class="step" style="margin-bottom:8px"><h2>À qui ?</h2></legend>
            @foreach($segments as $cle => $s)
                <label class="choice">
                    <input type="radio" name="segment" value="{{ $cle }}" required @checked(old('segment', 'inactifs') === $cle) data-nombre="{{ $s['nombre'] }}">
                    <span class="box" style="min-height:0;flex-direction:row;justify-content:space-between;align-items:center"><strong>{{ $s['libelle'] }}</strong><small>{{ $s['nombre'] }} personne(s)</small></span>
                </label>
            @endforeach
        </fieldset>
        <label class="fld" for="contenu">Message *
            <textarea id="contenu" name="contenu" rows="6" required minlength="10" maxlength="1000" data-contenu>{{ old('contenu', "Bonjour {prenom}, ce mois-ci à {salle} : -20 % sur le trimestriel avec le code RENTREE20. On vous attend !") }}</textarea>
            <span class="aide">Variables : {prenom}, {nom}, {fin} (fin de l'abonnement), {formule}, {jours_restants}, {salle}.</span>
        </label>
    </section>
    <section class="card dark recap">
        <h2>Aperçu</h2>
        <div class="bubble" style="background:#DCF8C6;color:#111;border-radius:12px 12px 12px 2px;padding:12px;font-size:14px;white-space:pre-line" data-apercu></div>
        <div class="total"><span class="muted">Destinataires</span><span class="num" data-nb>0</span></div>
        <button class="btn xl">Préparer les messages</button>
        <p class="meta" style="margin:0;color:var(--side-muted)">
            @if(config('salle.messagerie.driver') === 'whatsapp_cloud')
                Les messages partent automatiquement par WhatsApp Business.
            @else
                Les messages arrivent dans « À faire » : chacun s'envoie en un clic depuis WhatsApp.
            @endif
        </p>
    </section>
</form>
@endsection

@push('scripts')
<script>
(() => {
    const f = document.querySelector('[data-campagne]');
    const salle = @json(config('salle.nom'));
    const maj = () => {
        const txt = f.querySelector('[data-contenu]').value
            .replaceAll('{prenom}', 'Aminata').replaceAll('{nom}', 'Koné Aminata').replaceAll('{fin}', '10/10/2026')
            .replaceAll('{formule}', 'Mensuel').replaceAll('{jours_restants}', '12').replaceAll('{salle}', salle);
        f.querySelector('[data-apercu]').textContent = txt;
        f.querySelector('[data-nb]').textContent = f.querySelector('input[name="segment"]:checked')?.dataset.nombre ?? 0;
    };
    f.addEventListener('input', maj); f.addEventListener('change', maj); maj();
})();
</script>
@endpush
