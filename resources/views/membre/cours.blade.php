@extends('layouts.membre')
@section('title', 'Cours')

@section('content')
<h1 style="font-family:var(--f-display);font-size:34px;margin:0">Cours des 7 prochains jours</h1>

@forelse($seances->groupBy(fn ($s) => $s['date']->toDateString()) as $date => $duJour)
    <section class="card">
        <h2>{{ ucfirst(\Illuminate\Support\Carbon::parse($date)->translatedFormat('l j F')) }}</h2>
        @foreach($duJour as $s)
            @php($c = $s['cours'])
            @php($mienne = $mesReservations->get($c->id.'|'.$date))
            @php($complet = $s['inscrits'] >= $c->capacite)
            <div class="row">
                <span class="time">{{ $c->heureCourte() }}</span>
                <div class="grow">
                    <span class="name">{{ $c->nom }}</span>
                    <div class="meta">{{ $c->coach?->nom ?? 'Coach à définir' }} · {{ $c->duree_minutes }} min · {{ $complet ? 'complet' : ($c->capacite - $s['inscrits']).' place(s)' }}</div>
                </div>
                @if($mienne)
                    <form method="POST" action="{{ route('membre.reservations.annuler', $mienne) }}" data-confirmer="Annuler ma réservation ?" data-texte="Votre place sera donnée à la première personne en liste d'attente." data-bouton="Annuler la réservation" data-variante="danger">@csrf<button class="pill-btn" style="background:var(--surface-2);color:var(--muted)">{{ $mienne->statut === 'attente' ? 'Quitter l’attente' : 'Annuler' }}</button></form>
                @elseif(! ($s['date']->isToday() && $c->heureCourte() < now()->format('H:i')))
                    <form method="POST" action="{{ route('membre.cours.reserver', $c) }}">@csrf<input type="hidden" name="date" value="{{ $date }}"><button class="pill-btn">{{ $complet ? 'Liste d’attente' : 'Réserver' }}</button></form>
                @endif
            </div>
        @endforeach
    </section>
@empty
    <p class="empty">Aucun cours collectif prévu cette semaine.</p>
@endforelse
@endsection
