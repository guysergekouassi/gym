@extends('layouts.membre')
@section('title', 'Connexion')

@section('content')
<section class="card">
    <h1 style="font-family:var(--f-display);font-size:34px">Mon espace membre</h1>
    <p class="muted" style="margin:0">Vos jours restants, votre QR code d'accès, la réservation des cours et le renouvellement en ligne.</p>
    <p class="hint-card" style="margin:0">On se connecte avec le <strong>lien personnel</strong> reçu sur WhatsApp. Pas de mot de passe à retenir.</p>
    @if($envoiAuto)
        <form method="POST" action="{{ route('membre.connexion') }}" class="grid" style="gap:10px">
            @csrf
            <label class="fld" for="telephone">Votre numéro de téléphone<input id="telephone" type="tel" name="telephone" required placeholder="07 00 00 00 00" inputmode="tel"></label>
            <button class="btn xl">Recevoir mon lien sur WhatsApp</button>
        </form>
    @else
        <p style="margin:0"><strong>Lien perdu ?</strong> Demandez-le à l'accueil : il vous sera renvoyé sur WhatsApp en quelques secondes.</p>
    @endif
</section>
@endsection
