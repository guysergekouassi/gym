@extends('layouts.membre')
@section('title', 'Mon accès')

@php
    use App\Models\Cours;
    $jours = $finDroits ? (int) today()->diffInDays($finDroits, false) : null;
    $objectif = 3;
@endphp

@section('content')
<section class="hero-m {{ $gel ? 'gel' : (($finDroits || $abonnement) ? '' : 'ko') }}">
    <span style="font-weight:700;opacity:.9">Bonjour {{ $client->appel }}</span>
    @if($gel)
        <span class="num">Gelé</span><span>Jusqu'au {{ $gel->au->format('d/m/Y') }} · vos jours sont conservés</span>
    @elseif($finDroits)
        <span class="num">{{ $jours }} jour{{ $jours > 1 ? 's' : '' }}</span><span>{{ $abonnement?->formule?->nom }} · valable jusqu'au {{ $finDroits->format('d/m/Y') }}</span>
    @elseif($abonnement?->estCarnet())
        <span class="num">{{ $abonnement->entrees_restantes }} entrée{{ $abonnement->entrees_restantes > 1 ? 's' : '' }}</span><span>{{ $abonnement->formule->nom }} · jusqu'au {{ $abonnement->date_fin->format('d/m/Y') }}</span>
    @else
        <span class="num">Expiré</span><span>Renouvelez pour retrouver l'accès à la salle</span>
    @endif
    <div style="height:30px"></div>
</section>
<div class="qr-m"><img src="{{ route('membre.qr') }}" alt="Mon QR code d'accès"></div>
<p class="meta" style="text-align:center;margin:0">Présentez ce code au lecteur si vous n'avez pas votre carte · {{ $client->code_acces }}</p>

<a href="{{ route('membre.renouveler') }}" class="btn xl">{{ $finDroits ? 'Prolonger mon abonnement' : 'Renouveler mon abonnement' }}</a>

<section class="card">
    <div class="card-h"><h2>Cette semaine</h2><span class="tag {{ $semaine >= $objectif ? 'ok' : 'info' }}">{{ $semaine }} / {{ $objectif }} séances</span></div>
    <div class="meter" style="height:10px"><i style="width:{{ min(100, round(100 * $semaine / $objectif)) }}%"></i></div>
    <p class="meta" style="margin:0">{{ $semaine >= $objectif ? 'Objectif atteint, bravo !' : 'Encore '.($objectif - $semaine).' séance(s) pour atteindre votre objectif de la semaine.' }}</p>
</section>

@if($reservations->isNotEmpty())
<section class="card">
    <h2>Mes prochains cours</h2>
    @foreach($reservations as $r)
        <div class="row">
            <div class="grow"><span class="name">{{ $r->cours->nom }}</span><div class="meta">{{ ucfirst($r->date->translatedFormat('l j F')) }} à {{ $r->cours->heureCourte() }}</div></div>
            <span class="tag {{ $r->statut === 'attente' ? 'warn' : 'ok' }}">{{ $r->statut === 'attente' ? 'En attente' : 'Réservé' }}</span>
        </div>
    @endforeach
</section>
@endif

@if($packs->isNotEmpty())
<section class="card">
    <h2>Mon coaching</h2>
    @foreach($packs as $pack)
        <div class="row"><div class="grow"><span class="name">{{ $pack->seances_restantes }} séance(s) restante(s)</span><div class="meta">avec {{ $pack->coach?->nom ?? 'votre coach' }} · jusqu'au {{ $pack->expire_le?->format('d/m/Y') }}</div></div></div>
    @endforeach
</section>
@endif

<p class="meta" style="text-align:center;margin:0">Astuce : ajoutez cette page à l'écran d'accueil de votre téléphone pour l'ouvrir comme une application.</p>
@endsection
