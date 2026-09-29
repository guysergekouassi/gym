@extends('layouts.app')
@section('title', $caisse->nom)

@php
    use App\Models\Paiement;
    use App\Support\Fcfa;
    $estAujourdhui = $jour->isToday();
@endphp

@section('content')
<div class="top">
    <div>
        <div class="eyebrow"><a href="{{ route('admin.caisses.index') }}">← Caisses</a> · {{ $caisse->emplacement ?: 'Emplacement non précisé' }}</div>
        <h1>{{ $caisse->nom }}</h1>
    </div>
    <div class="daynav">
        <a class="icon-btn" href="{{ route('admin.caisses.show', [$caisse, 'date' => $jour->copy()->subDay()->toDateString()]) }}" aria-label="Jour précédent">@include('partials.icone', ['nom' => 'gauche'])</a>
        <div class="dlabel">
            <strong>{{ $estAujourdhui ? "Aujourd'hui" : ($jour->isYesterday() ? 'Hier' : ucfirst($jour->translatedFormat('l'))) }}</strong>
            <span>{{ $jour->translatedFormat('l j F Y') }}</span>
        </div>
        <a class="icon-btn" href="{{ $estAujourdhui ? '#' : route('admin.caisses.show', [$caisse, 'date' => $jour->copy()->addDay()->toDateString()]) }}"
           aria-label="Jour suivant" @if($estAujourdhui) aria-disabled="true" tabindex="-1" @endif>@include('partials.icone', ['nom' => 'droite'])</a>
    </div>
</div>

<div class="mini">
    <div><small>Total encaissé</small><div class="num">{{ Fcfa::format($resume['total']) }}</div></div>
    <div><small>Espèces à remettre</small><div class="num">{{ Fcfa::format($resume['especes']) }}</div></div>
    <div><small>Mobile Money &amp; carte</small><div class="num">{{ Fcfa::format($resume['electronique']) }}</div></div>
    <div><small>Reçus émis</small><div class="num">{{ $resume['nombre'] }}</div></div>
</div>

@if($cl = $resume['cloture'])
    <section class="card" style="border-color:{{ $cl->ecart === 0 ? 'var(--accent)' : 'var(--danger)' }}">
        <div class="card-h">
            <h2>Clôture de {{ $cl->created_at->format('H:i') }} par {{ $cl->user?->name ?? '—' }}</h2>
            <span class="tag {{ $cl->ecart === 0 ? 'ok' : 'ko' }}">{{ $cl->ecart === 0 ? 'Tiroir juste' : 'Écart '.($cl->ecart > 0 ? '+' : '').Fcfa::format($cl->ecart) }}</span>
        </div>
        <div class="meta">Fond {{ Fcfa::format($cl->fond_caisse) }} + espèces {{ Fcfa::format($cl->especes_encaissees) }} = attendu {{ Fcfa::format($cl->fond_caisse + $cl->especes_encaissees) }} · compté {{ Fcfa::format($cl->especes_comptees) }}{{ $cl->motif_ecart ? ' · motif : '.$cl->motif_ecart : '' }}</div>
    </section>
@elseif(! $jour->isToday() && $resume['nombre'])
    <p class="flash ko" style="margin:0">Cette journée n'a pas été clôturée.</p>
@endif

<div class="grid g3">
    <section class="card">
        <h2>Par mode de paiement</h2>
        @forelse($resume['par_mode'] as $mode => $montant)
            <div class="line"><span>{{ Paiement::MODES[$mode] ?? $mode }}</span><span class="num">{{ Fcfa::format($montant) }}</span></div>
        @empty
            <p class="empty">Aucun encaissement ce jour-là.</p>
        @endforelse
    </section>
    <section class="card">
        <h2>Par caissière</h2>
        @forelse($resume['par_caissier'] as $ligne)
            <div class="line"><span>{{ $ligne['nom'] }} · {{ $ligne['nombre'] }} reçu(s)</span><span class="num">{{ Fcfa::format($ligne['total']) }}</span></div>
        @empty
            <p class="empty">Aucun encaissement ce jour-là.</p>
        @endforelse
    </section>
    <section class="card">
        <h2>Caissières rattachées</h2>
        @forelse($caisse->caissiers as $caissier)
            <div class="line"><span>{{ $caissier->name }}</span>@unless($caissier->actif)<span class="tag off">Désactivée</span>@endunless</div>
        @empty
            <p class="empty">Aucune. <a href="{{ route('admin.caissiers.create') }}">Créer un compte caissière</a></p>
        @endforelse
    </section>
</div>

<div class="table-wrap">
    <table>
        <thead><tr><th>Heure</th><th>Reçu</th><th>Client</th><th>Objet</th><th>Paiement</th><th>Caissière</th><th class="r">Montant</th></tr></thead>
        <tbody>
        @forelse($resume['tous'] as $paiement)
            <tr class="{{ $paiement->estAnnule() ? 'refused' : '' }}">
                <td class="time">{{ $paiement->created_at->format('H:i') }}</td>
                <td><a href="{{ route('recus.show', $paiement) }}">{{ $paiement->numero_recu }}</a>@if($paiement->estAnnule())<div class="meta ko">Annulé par {{ $paiement->annulePar?->name }} : {{ $paiement->motif_annulation }}</div>@endif</td>
                <td>{{ $paiement->client?->nom_complet ?? 'Client de passage' }}</td>
                <td>{{ $paiement->objet() }}</td>
                <td>{{ Paiement::MODES[$paiement->mode] ?? $paiement->mode }}@if($paiement->reference)<div class="meta">Réf. {{ $paiement->reference }}</div>@endif</td>
                <td>{{ $paiement->user?->name ?? '—' }}</td>
                <td class="r"><span class="num" style="font-size:17px">{{ Fcfa::format($paiement->montant) }}</span></td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">Aucun encaissement ce jour-là.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
