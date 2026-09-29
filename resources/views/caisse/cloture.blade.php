@extends('layouts.app')
@section('title', 'Clôturer la caisse')

@php
    use App\Support\Fcfa;
    $cloture = $resume['cloture'];
@endphp

@section('content')
<div class="top">
    <div>
        <div class="eyebrow"><a href="{{ route('caisse.index') }}">← Caisse</a> · {{ $caisse->nom }} · {{ now()->translatedFormat('l j F Y') }}</div>
        <h1>Clôturer la caisse</h1>
    </div>
</div>

@if($cloture)
    <section class="card form-card">
        <h2>Caisse déjà clôturée</h2>
        <p class="muted" style="margin:0">Clôturée à {{ $cloture->created_at->format('H:i') }} par {{ $cloture->user?->name ?? '—' }}.</p>
        <div class="cash-split">
            <div>Espèces comptées<div class="num">{{ Fcfa::format($cloture->especes_comptees) }}</div></div>
            <div>Écart<div class="num" style="color:{{ $cloture->ecart === 0 ? 'var(--accent-ink)' : 'var(--danger)' }}">{{ ($cloture->ecart > 0 ? '+' : '').Fcfa::format($cloture->ecart) }}</div></div>
        </div>
        @if($cloture->motif_ecart)<p class="meta" style="margin:0">Motif : {{ $cloture->motif_ecart }}</p>@endif
    </section>
@else
<form method="POST" action="{{ route('caisse.cloture') }}" class="cash" data-cloture data-confirmer="Clôturer la journée ?" data-texte="{texte} Plus aucun encaissement ne sera possible aujourd'hui sur cette caisse." data-texte-depuis="[data-ecart-texte]" data-bouton="Clôturer" data-variante="danger">
    @csrf
    <section class="card">
        <h2>Comptez le tiroir</h2>
        <p class="sub" style="margin:0">Saisissez le nombre de billets et de pièces. Le total se calcule tout seul.</p>
        <div class="grid" style="gap:8px">
            @foreach($coupures as $valeur)
                <div class="count">
                    <label for="c{{ $valeur }}">{{ $valeur >= 1000 ? 'Billet' : ($valeur === 500 ? 'Billet ou pièce' : 'Pièce') }} de {{ number_format($valeur, 0, ',', ' ') }}</label>
                    <input id="c{{ $valeur }}" type="number" name="coupures[{{ $valeur }}]" min="0" inputmode="numeric" value="{{ old('coupures.'.$valeur, 0) }}" data-valeur="{{ $valeur }}">
                    <span class="num" data-ligne="{{ $valeur }}">0</span>
                </div>
            @endforeach
        </div>
    </section>

    <section class="card dark recap">
        <h2>Résultat</h2>
        <label class="fld" for="fond_caisse" style="color:var(--side-muted)">Fond de caisse du matin (FCFA)
            <input id="fond_caisse" type="number" name="fond_caisse" min="0" step="500" value="{{ old('fond_caisse', $fond) }}" required data-fond>
        </label>
        <dl>
            <div><dt>Espèces encaissées (GymFlow)</dt><dd class="num" style="font-size:20px">{{ Fcfa::format($resume['especes']) }}</dd></div>
            <div><dt>Attendu dans le tiroir</dt><dd class="num" style="font-size:22px" data-attendu>—</dd></div>
            <div><dt>Compté</dt><dd class="num" style="font-size:22px" data-compte>0</dd></div>
        </dl>
        <div class="total"><span>Écart</span><span class="num" data-ecart>0</span></div>
        <p class="hint" data-ecart-texte></p>
        <label class="fld" for="motif_ecart" style="color:var(--side-muted)" data-motif hidden>Raison de l'écart (obligatoire)
            <input id="motif_ecart" type="text" name="motif_ecart" value="{{ old('motif_ecart') }}" maxlength="255" placeholder="ex. monnaie rendue en trop à 15 h">
        </label>
        <div class="line" style="color:var(--side-muted)"><span>Mobile Money et carte (pour information)</span><span class="num">{{ Fcfa::format($resume['electronique']) }}</span></div>
        <button class="btn xl" data-valider>Clôturer la journée</button>
        <p class="meta" style="margin:0;color:var(--side-muted)">Après la clôture, plus aucun encaissement n'est possible aujourd'hui sur cette caisse. L'administrateur voit le résultat dans le journal de la caisse.</p>
    </section>
</form>
@endif
@endsection

@push('scripts')
<script>
(() => {
    const form = document.querySelector('[data-cloture]'); if (!form) return;
    const ENCAISSE = {{ (int) $resume['especes'] }};
    const fmt = (n) => new Intl.NumberFormat('fr-FR').format(n).replace(/ | /g, ' ');
    function calcul() {
        let compte = 0;
        form.querySelectorAll('[data-valeur]').forEach((i) => {
            const s = Math.max(0, +i.value || 0) * +i.dataset.valeur; compte += s;
            form.querySelector(`[data-ligne="${i.dataset.valeur}"]`).textContent = fmt(s);
        });
        const attendu = (+form.querySelector('[data-fond]').value || 0) + ENCAISSE;
        const ecart = compte - attendu;
        form.querySelector('[data-attendu]').textContent = fmt(attendu) + ' F';
        form.querySelector('[data-compte]').textContent = fmt(compte) + ' F';
        const e = form.querySelector('[data-ecart]');
        e.textContent = (ecart > 0 ? '+' : '') + fmt(ecart) + ' F';
        e.className = 'num ' + (ecart === 0 ? 'ecart-ok' : 'ecart-ko');
        form.querySelector('[data-ecart-texte]').textContent = ecart === 0 ? 'Le tiroir est juste.' : ecart < 0 ? `Il manque ${fmt(-ecart)} F. Recomptez, ou indiquez la raison.` : `Il y a ${fmt(ecart)} F de trop. Un encaissement a peut-être été oublié.`;
        form.querySelector('[data-motif]').hidden = ecart === 0;
        form.querySelector('#motif_ecart').required = ecart !== 0;
        form.querySelector('[data-valider]').textContent = ecart === 0 ? 'Clôturer la journée' : 'Clôturer avec cet écart';
    }
    form.addEventListener('input', calcul);
    calcul();
})();
</script>
@endpush
