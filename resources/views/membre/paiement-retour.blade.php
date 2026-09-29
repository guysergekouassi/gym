@extends('layouts.membre')
@section('title', 'Paiement')

@section('content')
@if($paiement->statut === 'accepte')
    <section class="hero-m"><span class="num">Merci !</span><span>Votre abonnement {{ $paiement->formule->nom }} est enregistré. Reçu {{ $paiement->paiement?->numero_recu }}.</span></section>
    <a href="{{ route('membre.accueil') }}" class="btn xl">Voir mes jours restants</a>
@elseif($paiement->statut === 'refuse')
    <section class="hero-m ko"><span class="num">Paiement refusé</span><span>Aucun montant n'a été prélevé. Vous pouvez réessayer ou payer à l'accueil.</span></section>
    <a href="{{ route('membre.renouveler') }}" class="btn xl">Réessayer</a>
@else
    <section class="card"><h2>Paiement en cours de confirmation</h2><p style="margin:0">Nous attendons la confirmation de l'opérateur. Cette page se met à jour toute seule.</p></section>
    <script>setTimeout(() => location.reload(), 5000);</script>
@endif
@endsection
