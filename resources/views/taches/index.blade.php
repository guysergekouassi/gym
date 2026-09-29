@extends('layouts.app')
@section('title', 'À faire')

@php
    use App\Models\Message;
    $auto = config('salle.messagerie.driver') === 'whatsapp_cloud';
@endphp

@section('content')
<div class="top">
    <div>
        <div class="eyebrow">{{ ucfirst(now()->translatedFormat('l j F')) }} · rappels préparés automatiquement chaque matin</div>
        <h1>À faire</h1>
    </div>
    <span class="tag {{ $total ? 'warn' : 'ok' }}" style="font-size:14px;padding:8px 14px">{{ $total ? "{$total} message(s) à envoyer" : 'Tout est à jour' }}</span>
</div>

@unless($auto)
    <p class="hint-card" style="margin:0">Pour chaque message, cliquez sur <strong>WhatsApp</strong> : la conversation s'ouvre avec le texte déjà écrit, il suffit d'appuyer sur Envoyer. Revenez ensuite cliquer sur <strong>Envoyé</strong>.</p>
@endunless
@if($echecs)
    <div class="flash ko">{{ $echecs }} message(s) automatique(s) n'ont pas pu partir cette semaine. Vérifiez la configuration WhatsApp.</div>
@endif

<div class="grid g3" style="align-items:start">
    <section class="card span2" aria-labelledby="t-messages">
        <div class="card-h">
            <h2 id="t-messages">Messages à envoyer</h2>
            <div class="seg" role="group" aria-label="Type de message">
                <a href="{{ route('taches.index') }}" aria-current="{{ $type ? 'false' : 'true' }}">Tout · {{ $total }}</a>
                @foreach(Message::TYPES as $cle => $libelle)
                    @if($compteParType->get($cle))
                        <a href="{{ route('taches.index', ['type' => $cle]) }}" aria-current="{{ $type === $cle ? 'true' : 'false' }}">{{ $libelle }} · {{ $compteParType->get($cle) }}</a>
                    @endif
                @endforeach
            </div>
        </div>
        <div class="rows">
            @forelse($messages as $message)
                <div class="row" style="align-items:flex-start">
                    <div class="av">{{ $message->client?->initiales ?? '?' }}</div>
                    <div class="grow">
                        <div class="name">
                            @if($message->client)<a href="{{ route('clients.show', $message->client) }}" style="color:inherit">{{ $message->client->nom_complet }}</a>@else{{ $message->prospect?->nom ?? $message->telephone }}@endif
                            <span class="tag info">{{ Message::TYPES[$message->type] ?? $message->type }}</span>
                        </div>
                        <p class="meta" style="margin:4px 0 0;white-space:pre-line;color:var(--fg)">{{ $message->contenu }}</p>
                        <div class="meta">{{ $message->telephone }}</div>
                    </div>
                    <div class="actions" style="flex-direction:column;align-items:stretch">
                        @if($lien = $message->lienWhatsapp())
                            <a href="{{ $lien }}" target="_blank" rel="noopener" class="btn sm" style="background:#1E8E57">@include('partials.icone', ['nom' => 'whatsapp', 'taille' => 15])WhatsApp</a>
                        @endif
                        <form method="POST" action="{{ route('messages.envoye', $message) }}">@csrf<button class="btn ghost sm" style="width:100%">Envoyé</button></form>
                        <form method="POST" action="{{ route('messages.ignorer', $message) }}">@csrf<button class="pill-btn" style="width:100%;background:transparent;color:var(--muted)">Ignorer</button></form>
                    </div>
                </div>
            @empty
                <p class="empty">Aucun message en attente. Les rappels d'échéance, les relances des inactifs et les anniversaires arrivent ici chaque matin.</p>
            @endforelse
        </div>
    </section>

    <div class="grid">
        <section class="card" aria-labelledby="t-essais">
            <div class="card-h"><h2 id="t-essais">Essais du jour</h2><a href="{{ route('prospects.index') }}">Prospects →</a></div>
            <div class="rows">
                @forelse($essais as $prospect)
                    <div class="row">
                        <span class="time">{{ $prospect->essai_le->format('H:i') }}</span>
                        <div class="grow"><span class="name">{{ $prospect->nom }}</span><div class="meta">{{ $prospect->telephone ?? '—' }} · {{ $prospect->source ?? 'source inconnue' }}</div></div>
                    </div>
                @empty
                    <p class="empty">Aucune séance d'essai prévue aujourd'hui.</p>
                @endforelse
            </div>
        </section>

        <section class="card alert" aria-labelledby="t-regul">
            <div class="card-h"><h2 id="t-regul">Refus à régulariser</h2><span class="tag ko">{{ $aRegulariser->count() }}</span></div>
            <div class="rows">
                @forelse($aRegulariser as $refus)
                    <div class="row">
                        <span class="time">{{ $refus->passe_le->format('H:i') }}</span>
                        <div class="grow"><span class="name">{{ $refus->client?->nom_complet ?? $refus->identification() }}</span><div class="meta ko">{{ $refus->message() }}</div></div>
                    </div>
                @empty
                    <p class="empty">Aucun refus en attente.</p>
                @endforelse
            </div>
            @if($aRegulariser->isNotEmpty() && auth()->user()->isCaissier())<a href="{{ route('caisse.index') }}">Régulariser à la caisse →</a>@endif
        </section>
    </div>
</div>
@endsection
