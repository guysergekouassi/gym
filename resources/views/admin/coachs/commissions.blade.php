@extends('layouts.app')
@section('title', 'Commissions des coachs')

@php use App\Support\Fcfa; @endphp

@section('content')
<div class="top">
    <div><div class="eyebrow"><a href="{{ route('admin.coachs.index') }}">← Coachs</a> · séances de coaching personnel faites dans le mois</div><h1>Commissions · {{ ucfirst($mois->translatedFormat('F Y')) }}</h1></div>
    <div class="actions">
        <a class="btn ghost sm" href="{{ route('admin.coachs.commissions', ['mois' => $mois->copy()->subMonth()->format('Y-m')]) }}">← Mois précédent</a>
        @unless($mois->isSameMonth(today()))<a class="btn ghost sm" href="{{ route('admin.coachs.commissions', ['mois' => $mois->copy()->addMonth()->format('Y-m')]) }}">Mois suivant →</a>@endunless
    </div>
</div>

<div class="grid g3">
    @forelse($parCoach as $ligne)
        <section class="card">
            <div class="card-h"><h2>{{ $ligne['coach']?->nom ?? 'Coach non renseigné' }}</h2><span class="tag info">{{ $ligne['coach']?->commission_pct ?? 0 }} %</span></div>
            <div class="num" style="font-size:36px">{{ Fcfa::format($ligne['commission']) }}</div>
            <div class="meta">{{ $ligne['seances'] }} séance(s) · valeur {{ Fcfa::format($ligne['valeur']) }}</div>
            <details class="panel">
                <summary>Détail des séances</summary>
                <div class="inner rows">
                    @foreach($ligne['detail'] as $s)
                        <div class="row"><span class="time">{{ $s->faite_le->format('d/m') }}</span><div class="grow"><span class="name">{{ $s->pack->client->nom_complet }}</span><div class="meta">{{ $s->pack->formule?->nom }}</div></div><span class="num" style="font-size:15px">{{ Fcfa::format($s->pack->prixSeance()) }}</span></div>
                    @endforeach
                </div>
            </details>
        </section>
    @empty
        <p class="empty">Aucune séance de coaching enregistrée ce mois-ci. Les séances se déclarent depuis la fiche du client (« Séance faite »).</p>
    @endforelse
</div>
@endsection
