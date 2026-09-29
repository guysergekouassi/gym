@extends('layouts.membre')
@section('title', 'Historique')

@php
    use App\Models\Paiement;
    use App\Support\Fcfa;
@endphp

@section('content')
<h1 style="font-family:var(--f-display);font-size:34px;margin:0">Historique</h1>

<section class="card">
    <h2>Mes paiements</h2>
    @forelse($paiements as $p)
        <div class="row">
            <div class="grow"><span class="name">{{ $p->objet() }}</span><div class="meta">{{ $p->created_at->format('d/m/Y') }} · {{ Paiement::MODES[$p->mode] ?? $p->mode }} · reçu {{ $p->numero_recu }}</div></div>
            <span class="num" style="font-size:17px">{{ Fcfa::format($p->montant) }}</span>
        </div>
    @empty
        <p class="empty">Aucun paiement.</p>
    @endforelse
</section>

<section class="card">
    <h2>Mes passages</h2>
    @forelse($passages as $p)
        <div class="row">
            <div class="grow"><span class="name">{{ ucfirst($p->passe_le->translatedFormat('l j F')) }}</span><div class="meta">{{ $p->passe_le->format('H:i') }}</div></div>
            <span class="tag {{ $p->estAutorise() ? 'ok' : 'ko' }}">{{ $p->estAutorise() ? 'Entrée' : $p->message() }}</span>
        </div>
    @empty
        <p class="empty">Aucun passage pour l'instant.</p>
    @endforelse
</section>
@endsection
