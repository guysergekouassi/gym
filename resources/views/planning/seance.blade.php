@extends('layouts.app')
@section('title', $cours->nom)

@php
    use App\Models\Reservation;
    $inscrits = $reservations->whereIn('statut', ['reservee', 'presente'])->count();
    $classes = ['reservee' => 'info', 'attente' => 'warn', 'presente' => 'ok', 'absente' => 'ko', 'annulee' => 'off'];
@endphp

@section('content')
<div class="top">
    <div>
        <div class="eyebrow"><a href="{{ route('planning.index', ['semaine' => $jour->toDateString()]) }}">← Planning</a> · {{ ucfirst($jour->translatedFormat('l j F')) }} à {{ $cours->heureCourte() }} · {{ $cours->duree_minutes }} min</div>
        <h1>{{ $cours->nom }}</h1>
    </div>
    <span class="tag {{ $inscrits >= $cours->capacite ? 'warn' : 'ok' }}" style="font-size:14px;padding:8px 14px">{{ $inscrits }} / {{ $cours->capacite }} places · {{ $cours->coach?->nom ?? 'coach à définir' }}</span>
</div>

<div class="grid g3" style="align-items:start">
    <section class="card span2">
        <h2>Inscrits</h2>
        <div class="rows">
            @forelse($reservations as $r)
                <div class="row">
                    <div class="av">{{ $r->client->initiales }}</div>
                    <div class="grow"><a class="name" href="{{ route('clients.show', $r->client) }}">{{ $r->client->nom_complet }}</a><div class="meta">{{ $r->client->telephone }}</div></div>
                    <span class="tag {{ $classes[$r->statut] }}">{{ Reservation::STATUTS[$r->statut] }}</span>
                    @if(in_array($r->statut, ['reservee', 'attente', 'presente', 'absente'], true))
                        <form method="POST" action="{{ route('reservations.statut', $r) }}" class="actions">
                            @csrf
                            @if($r->statut !== 'attente')
                                <button name="statut" value="presente" class="pill-btn">Présent</button>
                                <button name="statut" value="absente" class="pill-btn" style="background:var(--danger-soft);color:var(--danger)">Absent</button>
                            @endif
                            <button name="statut" value="annulee" class="pill-btn" style="background:transparent;color:var(--muted)">Annuler</button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="empty">Personne n'a encore réservé.</p>
            @endforelse
        </div>
    </section>

    @unless($jour->lt(today()))
    <form method="POST" action="{{ route('planning.inscrire', [$cours, $jour->toDateString()]) }}" class="card" data-inscrire>
        @csrf
        <h2>Inscrire un membre</h2>
        <input type="hidden" name="client_id" data-client-id required>
        <div class="search">
            <label class="fld" for="q-seance">Nom, téléphone ou n° d'empreinte<input id="q-seance" type="search" autocomplete="off" data-q></label>
            <ul class="results" data-res hidden></ul>
        </div>
        <p class="meta" data-choisi>Aucun membre choisi.</p>
        <button class="btn">Inscrire</button>
        <p class="meta" style="margin:0">Si le cours est complet, le membre passe en liste d'attente et récupère la place au premier désistement.</p>
    </form>
    @endunless
</div>
@endsection

@push('scripts')
<script>
(() => {
    const f = document.querySelector('[data-inscrire]'); if (!f) return;
    const champ = f.querySelector('[data-q]'), liste = f.querySelector('[data-res]');
    let t = null;
    champ.addEventListener('input', () => {
        clearTimeout(t); const q = champ.value.trim();
        if (q.length < 2) { liste.hidden = true; return; }
        t = setTimeout(async () => {
            const r = await fetch(@json(route('recherche')) + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
            const { clients } = await r.json();
            liste.replaceChildren();
            clients.forEach((c) => {
                const li = document.createElement('li'), b = document.createElement('button'), s = document.createElement('small');
                b.type = 'button'; b.textContent = c.nom; s.textContent = c.statut + (c.telephone ? ' · ' + c.telephone : ''); b.append(s);
                b.addEventListener('click', () => { f.querySelector('[data-client-id]').value = c.id; f.querySelector('[data-choisi]').textContent = '✓ ' + c.nom; liste.hidden = true; champ.value = ''; });
                li.append(b); liste.append(li);
            });
            liste.hidden = !clients.length;
        }, 200);
    });
})();
</script>
@endpush
