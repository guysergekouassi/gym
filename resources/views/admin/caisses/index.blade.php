@extends('layouts.app')
@section('title', 'Caisses')

@php
    use App\Support\Fcfa;
@endphp

@section('content')
<div class="top">
    <div>
        <div class="eyebrow">Points d'encaissement · recettes d'aujourd'hui</div>
        <h1>Caisses</h1>
    </div>
    <div class="actions">
        <a href="{{ route('admin.caissiers.index') }}" class="btn ghost">Gérer les caissières</a>
        <a href="{{ route('admin.caisses.create') }}" class="btn">@include('partials.icone', ['nom' => 'plus'])Nouvelle caisse</a>
    </div>
</div>

@if($caisses->isEmpty())
    <section class="card form-card">
        <h2>Aucune caisse</h2>
        <p class="empty">Créez une première caisse, puis rattachez-y une caissière pour qu'elle puisse encaisser.</p>
        <a href="{{ route('admin.caisses.create') }}" class="btn" style="align-self:flex-start">Créer une caisse</a>
    </section>
@else
    <div class="grid g3">
        @foreach($caisses as $caisse)
            @php($r = $resumes[$caisse->id])
            <section class="card" aria-labelledby="caisse-{{ $caisse->id }}">
                <div class="card-h">
                    <div>
                        <h2 id="caisse-{{ $caisse->id }}">{{ $caisse->nom }}</h2>
                        <div class="sub">{{ $caisse->salle?->nom ? $caisse->salle->nom.' · ' : '' }}{{ $caisse->emplacement ?: 'Emplacement non précisé' }}</div>
                    </div>
                    <span class="tag {{ $r['cloture'] ? 'warn' : ($caisse->actif ? 'ok' : 'off') }}">{{ $r['cloture'] ? 'Clôturée' : ($caisse->actif ? 'Active' : 'Désactivée') }}</span>
                </div>
                <div>
                    <div class="num" style="font-size:38px">{{ Fcfa::format($r['total']) }}</div>
                    <div class="meta">{{ $r['nombre'] }} reçu(s) · {{ $r['journaliers'] }} journalier(s) · {{ $r['abonnements'] }} abonnement(s)</div>
                </div>
                <div class="cash-split">
                    <div>Espèces<div class="num">{{ Fcfa::format($r['especes']) }}</div></div>
                    <div>Mobile Money &amp; carte<div class="num">{{ Fcfa::format($r['electronique']) }}</div></div>
                </div>
                <div>
                    <div class="cap" style="color:var(--muted)">Caissières</div>
                    <div class="rows">
                        @forelse($caisse->caissiers as $caissier)
                            <div class="row">
                                <div class="grow"><span class="name">{{ $caissier->name }}</span><div class="meta">{{ $caissier->email }}</div></div>
                                @unless($caissier->actif)<span class="tag off">Compte désactivé</span>@endunless
                            </div>
                        @empty
                            <p class="empty">Aucune caissière rattachée : personne ne peut encaisser sur cette caisse.</p>
                        @endforelse
                    </div>
                </div>
                <div class="actions" style="margin-top:auto">
                    <a href="{{ route('admin.caisses.show', $caisse) }}" class="btn sm">Journal</a>
                    <a href="{{ route('admin.caisses.edit', $caisse) }}" class="btn ghost sm">Modifier</a>
                </div>
            </section>
        @endforeach
    </div>
@endif
@endsection
