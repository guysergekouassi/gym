@extends('layouts.membre')
@section('title', 'Ma progression')

@php $poids = $mesures->whereNotNull('poids')->values(); @endphp

@section('content')
<h1 style="font-family:var(--f-display);font-size:34px;margin:0">Ma progression</h1>

@if($client->objectif)
    <section class="card"><div class="meta" style="font-weight:700">Mon objectif</div><p style="margin:0;font-size:17px;font-weight:600">{{ $client->objectif }}</p></section>
@endif

<section class="card">
    <h2>Poids</h2>
    @if($poids->count() > 1)
        @php
            $min = $poids->min('poids') - 1; $max = $poids->max('poids') + 1; $n = $poids->count();
            $pts = $poids->map(fn ($m, $i) => [round(20 + $i * 360 / max(1, $n - 1), 1), round(70 - ($m->poids - $min) * 55 / max(0.1, $max - $min), 1)]);
            $ligne = $pts->map(fn ($p, $i) => ($i ? 'L' : 'M').$p[0].' '.$p[1])->implode(' ');
            $diff = round($poids->last()->poids - $poids->first()->poids, 1);
        @endphp
        <div class="num" style="font-size:40px">{{ $poids->last()->poids }} kg <span class="tag {{ $diff <= 0 ? 'ok' : 'warn' }}" style="font-family:var(--f-body);font-size:13px;vertical-align:middle">{{ $diff > 0 ? '+' : '' }}{{ $diff }} kg depuis le {{ $poids->first()->date->format('d/m') }}</span></div>
        <svg class="spark" viewBox="0 0 400 90" role="img" aria-label="Évolution du poids">
            <path class="a" d="{{ $ligne }} L{{ $pts->last()[0] }} 80 L{{ $pts->first()[0] }} 80 Z"></path>
            <path class="l" d="{{ $ligne }}"></path>
            @foreach($pts as $p)<circle cx="{{ $p[0] }}" cy="{{ $p[1] }}" r="3.5"></circle>@endforeach
        </svg>
    @elseif($poids->count() === 1)
        <div class="num" style="font-size:40px">{{ $poids->first()->poids }} kg</div><p class="meta" style="margin:0">Mesuré le {{ $poids->first()->date->format('d/m/Y') }}. La courbe apparaîtra à la prochaine mesure.</p>
    @else
        <p class="empty">Aucune mesure pour l'instant. Demandez à votre coach ou à l'accueil de noter vos mesures.</p>
    @endif
</section>

@if($client->programme)
    <section class="card"><h2>Mon programme</h2><p style="margin:0;white-space:pre-line">{{ $client->programme }}</p></section>
@endif
@endsection
