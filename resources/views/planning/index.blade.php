@extends('layouts.app')
@section('title', 'Planning des cours')

@section('content')
<div class="top">
    <div>
        <div class="eyebrow">Semaine du {{ $lundi->translatedFormat('j F') }} au {{ $lundi->copy()->addDays(6)->translatedFormat('j F Y') }}</div>
        <h1>Planning des cours</h1>
    </div>
    <div class="actions">
        <a class="btn ghost sm" href="{{ route('planning.index', ['semaine' => $lundi->copy()->subWeek()->toDateString()]) }}">← Semaine précédente</a>
        <a class="btn ghost sm" href="{{ route('planning.index') }}">Cette semaine</a>
        <a class="btn ghost sm" href="{{ route('planning.index', ['semaine' => $lundi->copy()->addWeek()->toDateString()]) }}">Semaine suivante →</a>
        @if(auth()->user()->isAdmin())<a class="btn sm" href="{{ route('admin.cours.index') }}">Gérer les cours</a>@endif
    </div>
</div>

@if($cours->isEmpty())
    <section class="card form-card">
        <h2>Aucun cours au planning</h2>
        <p class="empty">{{ auth()->user()->isAdmin() ? 'Ajoutez vos cours collectifs (zumba, cross-training…) : ils se répètent chaque semaine.' : 'L’administrateur n’a pas encore créé de cours collectifs.' }}</p>
        @if(auth()->user()->isAdmin())<a href="{{ route('admin.cours.create') }}" class="btn" style="align-self:flex-start">Ajouter un cours</a>@endif
    </section>
@else
    <div class="plan-wrap">
        <div class="plan">
            <div></div>
            @foreach($jours as $jour)<div class="h {{ $jour->isToday() ? 'today' : '' }}">{{ ucfirst($jour->translatedFormat('D j')) }}</div>@endforeach
            @foreach($heures as $heure)
                <div class="t">{{ $heure }}</div>
                @foreach($jours as $jour)
                    @php($c = $cours->first(fn ($x) => $x->jour_semaine === $jour->isoWeekday() && $x->heureCourte() === $heure))
                    @if($c)
                        @php($res = $reservations->get($c->id.'|'.$jour->toDateString(), collect()))
                        @php($inscrits = $res->whereIn('statut', ['reservee', 'presente'])->count())
                        @php($attente = $res->where('statut', 'attente')->count())
                        <a class="slot {{ $inscrits >= $c->capacite ? 'full' : '' }} {{ $jour->lt(today()) ? 'passe' : '' }}" href="{{ route('planning.seance', [$c, $jour->toDateString()]) }}">
                            <b>{{ $c->nom }}</b>
                            <span class="meta">{{ $c->coach?->nom ?? 'Coach à définir' }} · {{ $c->duree_minutes }} min</span>
                            <span class="meta"><b style="color:var(--fg)">{{ $inscrits }}/{{ $c->capacite }}</b>{{ $attente ? " · {$attente} en attente" : '' }}</span>
                            <div class="meter"><i style="width:{{ min(100, round(100 * $inscrits / max(1, $c->capacite))) }}%"></i></div>
                        </a>
                    @else
                        <div class="slot-vide"></div>
                    @endif
                @endforeach
            @endforeach
        </div>
    </div>
@endif
@endsection
