@extends('layouts.app')
@section('title', 'Lecteurs')

@section('content')
<div class="top">
    <div><div class="eyebrow"><a href="{{ route('admin.salles.index') }}">← Salles</a> · lecteurs d'empreinte, de badge ou de QR code</div><h1>Lecteurs</h1></div>
</div>

@if($nouveau = session('token_lecteur'))
    <section class="card" style="border-color:var(--accent)">
        <h2>Lecteur « {{ $nouveau['nom'] }} » créé</h2>
        <p class="meta" style="margin:0">Copiez ce token maintenant et configurez-le dans le lecteur ou l'agent local. <strong>Il ne sera plus jamais affiché.</strong></p>
        <div class="copy-box"><span data-token>{{ $nouveau['token'] }}</span><button type="button" class="btn sm ghost" data-copier>Copier</button></div>
    </section>
@endif

<div class="grid g3" style="align-items:start">
    <div class="table-wrap span2">
        <table>
            <thead><tr><th>Lecteur</th><th>Salle</th><th>Dernière activité</th><th>Actif</th><th><span class="sr">Actions</span></th></tr></thead>
            <tbody>
            @forelse($lecteurs as $lecteur)
                <tr>
                    <td class="name">{{ $lecteur->nom }}</td>
                    <td colspan="3">
                        <form method="POST" action="{{ route('admin.lecteurs.update', $lecteur) }}" class="inline-form" style="flex-wrap:nowrap;align-items:center" id="l-{{ $lecteur->id }}">
                            @csrf @method('PUT')
                            <label class="sr" for="s-{{ $lecteur->id }}">Salle</label>
                            <select id="s-{{ $lecteur->id }}" name="salle_id" class="btn ghost sm"><option value="">—</option>@foreach($salles as $s)<option value="{{ $s->id }}" @selected($lecteur->salle_id === $s->id)>{{ $s->nom }}</option>@endforeach</select>
                            <span class="meta" style="min-width:120px">{{ $lecteur->derniere_activite_at?->diffForHumans() ?? 'Jamais' }}</span>
                            <label class="check" for="a-{{ $lecteur->id }}" style="min-height:0"><input id="a-{{ $lecteur->id }}" type="checkbox" name="actif" value="1" @checked($lecteur->actif)> Actif</label>
                        </form>
                    </td>
                    <td class="r"><button form="l-{{ $lecteur->id }}" class="pill-btn">Enregistrer</button></td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">Aucun lecteur.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="grid">
        <form method="POST" action="{{ route('admin.lecteurs.store') }}" class="card">
            @csrf
            <h2>Nouveau lecteur</h2>
            <label class="fld" for="nom">Nom<input id="nom" name="nom" required maxlength="100" placeholder="ex. Entrée secondaire"></label>
            <label class="fld" for="salle_id">Salle<select id="salle_id" name="salle_id"><option value="">—</option>@foreach($salles as $s)<option value="{{ $s->id }}">{{ $s->nom }}</option>@endforeach</select></label>
            <button class="btn">Créer et afficher le token</button>
        </form>
        <div class="hint-card">
            <strong style="color:var(--fg)">Brancher un lecteur</strong><br>
            Empreinte : <code>POST {{ url('/api/pointage/empreinte') }}</code> avec <code>empreinte_id</code>.<br>
            Badge ou QR : <code>POST {{ url('/api/pointage/carte') }}</code> avec <code>carte</code>.<br>
            Token dans l'en-tête <code>Authorization: Bearer …</code>. Un lecteur USB branché sur le PC d'accueil fonctionne directement sur l'écran d'accueil, sans token.
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.querySelector('[data-copier]')?.addEventListener('click', async (e) => {
    const n = document.querySelector('[data-token]');
    try { await navigator.clipboard.writeText(n.textContent); e.target.textContent = 'Copié'; window.gfToast?.('Token copié'); }
    catch { const r = document.createRange(); r.selectNodeContents(n); getSelection().removeAllRanges(); getSelection().addRange(r); }
});
</script>
@endpush
