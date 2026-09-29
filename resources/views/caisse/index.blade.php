@extends('layouts.app')
@section('title', 'Caisse')

@php
    use App\Models\Client;
    use App\Models\Paiement;
    use App\Support\Fcfa;
    $tarifJournalier = (int) config('salle.tarif_journalier');
    $fraisInscription = (int) config('salle.frais_inscription');
    $client = $clientPreselectionne;
    $modes = Paiement::modesCaisse();
@endphp

@section('content')
<div class="top">
    <div>
        <div class="eyebrow">{{ $caisse->nom }}{{ $caisse->emplacement ? ' · '.$caisse->emplacement : '' }} · {{ now()->translatedFormat('l j F Y') }}</div>
        <h1>Caisse</h1>
    </div>
    <div class="actions">
        <a href="{{ route('clients.create') }}" class="btn ghost">@include('partials.icone', ['nom' => 'plus'])Nouveau client</a>
        <a href="{{ route('caisse.cloture') }}" class="btn ghost">@include('partials.icone', ['nom' => 'cadenas'])Clôturer la caisse</a>
    </div>
</div>

@if($cloturee)
    <div class="cloturee" role="status">@include('partials.icone', ['nom' => 'cadenas']) La caisse est clôturée pour aujourd'hui : plus aucun encaissement n'est possible jusqu'à demain.</div>
@endif

<div class="mini" aria-label="Ma journée">
    <div><small>Encaissé aujourd'hui</small><div class="num">{{ Fcfa::format($resume['total']) }}</div></div>
    <div><small>Reçus émis</small><div class="num">{{ $resume['nombre'] }}</div></div>
    <div><small>Entrées journalières</small><div class="num">{{ $resume['journaliers'] }}</div></div>
    <div><small>Abonnements · ventes</small><div class="num">{{ $resume['abonnements'] }} · {{ $resume['ventes'] }}</div></div>
</div>

<div class="cash">
    <div class="grid">
        @if($aRegulariser->isNotEmpty())
            <section class="card alert" aria-labelledby="t-regul">
                <div class="card-h">
                    <div><h2 id="t-regul">À régulariser</h2><div class="sub">Refusés à l'entrée aujourd'hui, pas encore passés à la caisse</div></div>
                    <span class="tag ko">{{ $aRegulariser->count() }}</span>
                </div>
                <div class="rows">
                    @foreach($aRegulariser as $refus)
                        <div class="row">
                            <span class="time">{{ $refus->passe_le->format('H:i') }}</span>
                            <div class="grow">
                                <div class="name">{{ $refus->client?->nom_complet ?? $refus->identification() }}</div>
                                <div class="meta ko">{{ $refus->message() }}</div>
                            </div>
                            @if(in_array($refus->motif, ['empreinte_inconnue', 'carte_inconnue'], true))
                                <a href="{{ route('clients.create', [$refus->motif === 'carte_inconnue' ? 'carte_id' : 'empreinte_id' => $refus->empreinte_id]) }}" class="pill-btn">Enrôler</a>
                            @elseif($refus->motif === 'paiement_requis')
                                <form method="POST" action="{{ route('caisse.journalier') }}">
                                    @csrf
                                    <input type="hidden" name="client_id" value="{{ $refus->client_id }}">
                                    <input type="hidden" name="montant" value="{{ $tarifJournalier }}">
                                    <input type="hidden" name="mode" value="especes">
                                    <button class="pill-btn" @disabled($cloturee)>Encaisser {{ Fcfa::format($tarifJournalier) }}</button>
                                </form>
                            @elseif($refus->motif === 'abonnement_gele')
                                <a href="{{ route('clients.show', $refus->client_id) }}" class="pill-btn">Voir le gel</a>
                            @else
                                <a href="{{ route('caisse.index', ['client_id' => $refus->client_id]) }}#abonnement" class="pill-btn">Renouveler</a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="card" id="abonnement" aria-labelledby="t-client">
            <div class="step"><span class="n">1</span><h2 id="t-client">Client</h2></div>
            <div class="search" data-recherche-client>
                <label class="fld" for="q-client">Rechercher par nom, téléphone, n° d'empreinte ou de carte
                    <input id="q-client" type="search" autocomplete="off" data-client-q placeholder="Au moins 2 caractères">
                </label>
                <ul class="results" data-client-resultats hidden></ul>
            </div>
            <div class="client" data-client-carte @if(! $client) hidden @endif>
                <div class="av" data-client-av>{{ $client?->initiales }}</div>
                <div class="grow">
                    <div class="name" style="font-size:17px;font-weight:700" data-client-nom>{{ $client?->nom_complet }}</div>
                    <div class="meta" data-client-meta>@if($client){{ Client::TYPES[$client->type] ?? $client->type }}{{ $client->telephone ? ' · '.$client->telephone : '' }}{{ $client->empreinte_id ? ' · Empreinte n° '.$client->empreinte_id : '' }}@endif</div>
                </div>
                <span data-client-tag class="tag {{ $finDroitsPreselectionne ? 'ok' : 'ko' }}">@if($client){{ $finDroitsPreselectionne ? 'Droits jusqu’au '.$finDroitsPreselectionne->format('d/m/Y') : 'Aucun abonnement en cours' }}@endif</span>
            </div>
            <p class="empty" data-client-vide @if($client) hidden @endif>Aucun client sélectionné. Si le client n'existe pas encore, <a href="{{ route('clients.create') }}">créez sa fiche</a>.</p>
        </section>

        <section class="card" aria-label="Ce que le client achète">
            <div class="onglets" role="tablist">
                <button type="button" role="tab" aria-selected="true" aria-controls="p-abo" id="o-abo">Abonnement ou carnet</button>
                @if($formulesCoaching->isNotEmpty())<button type="button" role="tab" aria-selected="false" aria-controls="p-coaching" id="o-coaching">Coaching</button>@endif
                @if($produits->isNotEmpty())<button type="button" role="tab" aria-selected="false" aria-controls="p-bar" id="o-bar">Bar et boutique</button>@endif
            </div>

            {{-- Abonnement / carnet --}}
            <form method="POST" action="{{ route('caisse.abonnement') }}" id="p-abo" role="tabpanel" aria-labelledby="o-abo" class="grid" data-form-abonnement data-avec-client data-confirmer="Encaisser l'abonnement ?" data-texte="Montant à encaisser : {texte}. Le reçu s'imprime ensuite." data-texte-depuis="[data-recap-total]" data-confirmer-si="[data-client-id]" data-bouton="Encaisser">
                @csrf
                <input type="hidden" name="client_id" value="{{ old('client_id', $client?->id) }}" data-client-id>
                <div class="step"><span class="n">2</span><h2>Formule</h2></div>
                <div class="choices f" role="radiogroup" aria-label="Formule">
                    @foreach($formules as $formule)
                        <label class="choice">
                            <input type="radio" name="formule_id" value="{{ $formule->id }}" required
                                   data-nom="{{ $formule->nom }}" data-prix="{{ $formule->prix }}" data-duree="{{ $formule->duree_jours }}" data-carnet="{{ $formule->estCarnet() ? 1 : 0 }}"
                                   @checked((int) old('formule_id', $formules->first()?->id) === $formule->id)>
                            <span class="box">
                                <strong>{{ $formule->nom }}</strong>
                                <span class="num">{{ number_format($formule->prix, 0, ',', ' ') }}</span>
                                <small>{{ $formule->resume() }}{{ $formule->categorie && $formule->categorie !== 'Standard' ? ' · '.$formule->categorie : '' }}</small>
                            </span>
                        </label>
                    @endforeach
                </div>

                <div class="step"><span class="n">3</span><h2>Paiement</h2></div>
                <div class="choices m" role="radiogroup" aria-label="Mode de paiement">
                    @foreach($modes as $valeur => $libelle)
                        <label class="mode">
                            <input type="radio" name="mode" value="{{ $valeur }}" required data-libelle="{{ $libelle }}" @checked(old('mode', 'especes') === $valeur)>
                            <span>{{ $libelle }}</span>
                        </label>
                    @endforeach
                </div>
                <div class="grid g2">
                    <label class="fld" for="reference" data-reference>Référence de la transaction
                        <input id="reference" type="text" name="reference" value="{{ old('reference') }}" placeholder="ex. MP260928.1840.A12345">
                    </label>
                    <label class="fld" for="code_promo">Code promo
                        <input id="code_promo" type="text" name="code_promo" value="{{ old('code_promo') }}" placeholder="Facultatif" style="text-transform:uppercase">
                    </label>
                </div>
                @if($fraisInscription > 0)
                    <label class="check" for="frais_inscription" data-frais @if(! $premierAbonnement) hidden @endif>
                        <input type="hidden" name="frais_inscription" value="0">
                        <input id="frais_inscription" type="checkbox" name="frais_inscription" value="1" checked>
                        Frais d'inscription : {{ Fcfa::format($fraisInscription) }} (premier abonnement)
                    </label>
                @endif
            </form>

            {{-- Coaching --}}
            @if($formulesCoaching->isNotEmpty())
            <form method="POST" action="{{ route('caisse.coaching') }}" id="p-coaching" role="tabpanel" aria-labelledby="o-coaching" class="grid" hidden data-avec-client data-confirmer="Encaisser le pack de coaching ?" data-confirmer-si="[data-client-id]" data-bouton="Encaisser">
                @csrf
                <input type="hidden" name="client_id" value="{{ $client?->id }}" data-client-id>
                <div class="choices f" role="radiogroup" aria-label="Pack de coaching">
                    @foreach($formulesCoaching as $formule)
                        <label class="choice">
                            <input type="radio" name="formule_id" value="{{ $formule->id }}" required @checked($loop->first)>
                            <span class="box"><strong>{{ $formule->nom }}</strong><span class="num">{{ number_format($formule->prix, 0, ',', ' ') }}</span><small>{{ $formule->resume() }}</small></span>
                        </label>
                    @endforeach
                </div>
                <div class="grid g2">
                    <label class="fld" for="coach_id">Coach
                        <select id="coach_id" name="coach_id">
                            <option value="">À définir</option>
                            @foreach($coachs as $coach)<option value="{{ $coach->id }}">{{ $coach->nom }}{{ $coach->specialite ? ' · '.$coach->specialite : '' }}</option>@endforeach
                        </select>
                    </label>
                    <label class="fld" for="mode-coaching">Paiement
                        <select id="mode-coaching" name="mode" required>@foreach($modes as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
                    </label>
                </div>
                <label class="fld" for="ref-coaching">Référence (Mobile Money)<input id="ref-coaching" type="text" name="reference" placeholder="Facultatif"></label>
                <button class="btn" @disabled($cloturee)>Encaisser le pack de coaching</button>
            </form>
            @endif

            {{-- Bar et boutique --}}
            @if($produits->isNotEmpty())
            <form method="POST" action="{{ route('caisse.vente') }}" id="p-bar" role="tabpanel" aria-labelledby="o-bar" class="grid" hidden data-form-vente data-confirmer="Encaisser la vente ?" data-texte="Total : {texte}" data-texte-depuis="[data-total-vente]" data-bouton="Encaisser">
                @csrf
                <div class="produits">
                    @foreach($produits as $produit)
                        <div class="produit" data-produit data-prix="{{ $produit->prix }}">
                            <div><div class="name">{{ $produit->nom }}</div><div class="meta">{{ Fcfa::format($produit->prix) }} · stock {{ $produit->stock }}</div></div>
                            <div class="qte">
                                <button type="button" data-moins aria-label="Retirer un {{ $produit->nom }}">−</button>
                                <input type="number" name="quantites[{{ $produit->id }}]" value="0" min="0" max="{{ max(0, $produit->stock) }}" aria-label="Quantité de {{ $produit->nom }}">
                                <button type="button" data-plus aria-label="Ajouter un {{ $produit->nom }}" @disabled($produit->stock < 1)>+</button>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="grid g2">
                    <label class="fld" for="mode-vente">Paiement
                        <select id="mode-vente" name="mode" required>@foreach($modes as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
                    </label>
                    <div class="fld">Total<div class="num" style="font-size:34px;color:var(--fg)" data-total-vente>0 FCFA</div></div>
                </div>
                <button class="btn" @disabled($cloturee)>Encaisser la vente</button>
            </form>
            @endif
        </section>
    </div>

    <div class="grid">
        <section class="card dark recap" aria-labelledby="t-recap">
            <h2 id="t-recap">Récapitulatif de l'abonnement</h2>
            <dl>
                <div><dt>Client</dt><dd data-recap-client>{{ $client?->nom_complet ?? '—' }}</dd></div>
                <div><dt>Formule</dt><dd data-recap-formule>—</dd></div>
                <div><dt>Période</dt><dd data-recap-periode>—</dd></div>
                <div><dt>Paiement</dt><dd data-recap-mode>—</dd></div>
                <div data-recap-frais-ligne hidden><dt>Frais d'inscription</dt><dd>{{ Fcfa::format($fraisInscription) }}</dd></div>
            </dl>
            <div class="hint">Renouvellement anticipé : aucun jour perdu. Un code promo valable est déduit à l'encaissement.</div>
            <div class="total"><span class="muted">Total</span><span class="num" data-recap-total>—</span></div>
            <button class="btn xl" form="p-abo" @disabled($cloturee)>@include('partials.icone', ['nom' => 'imprimer'])Encaisser et imprimer le reçu</button>
            <p class="hint" data-erreur-client hidden style="color:#FFB4AB">Sélectionnez d'abord le client.</p>
        </section>

        <form method="POST" action="{{ route('caisse.journalier') }}" class="card" id="journalier" aria-labelledby="t-journalier">
            @csrf
            <div><h2 id="t-journalier">Entrée journalière</h2><div class="sub">Nom et téléphone facultatifs : avec un téléphone, le client est retrouvé la prochaine fois</div></div>
            <div class="split">
                <label class="fld" for="j-nom">Nom<input id="j-nom" type="text" name="nom" value="{{ old('nom') }}" placeholder="Facultatif"></label>
                <label class="fld" for="j-tel">Téléphone<input id="j-tel" type="tel" name="telephone" value="{{ old('telephone') }}" placeholder="Facultatif"></label>
            </div>
            <div class="split">
                <label class="fld" for="j-montant">Montant (FCFA)<input id="j-montant" type="number" name="montant" min="0" step="100" required value="{{ old('montant', $tarifJournalier) }}"></label>
                <label class="fld" for="j-mode">Paiement
                    <select id="j-mode" name="mode" required>@foreach($modes as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
                </label>
            </div>
            <label class="fld" for="j-ref">Référence (Mobile Money)<input id="j-ref" type="text" name="reference" placeholder="Facultatif"></label>
            <button class="btn ghost" @disabled($cloturee)>Encaisser l'entrée et valider l'accès</button>
        </form>

        <section class="card" aria-labelledby="t-jour">
            <div class="card-h"><h2 id="t-jour">Caisse du jour</h2><span class="num" style="font-size:24px">{{ Fcfa::format($resume['total']) }}</span></div>
            <div class="rows">
                @forelse($resume['tous']->take(10) as $paiement)
                    <div class="row">
                        <span class="time" style="width:44px">{{ $paiement->created_at->format('H:i') }}</span>
                        <div class="grow">
                            <a class="name" href="{{ route('recus.show', $paiement) }}" @if($paiement->estAnnule()) style="text-decoration:line-through" @endif>{{ $paiement->client?->nom_complet ?? Paiement::TYPES[$paiement->type] }}</a>
                            <div class="meta">{{ $paiement->objet() }} · {{ Paiement::MODES[$paiement->mode] ?? $paiement->mode }}</div>
                        </div>
                        @if($paiement->estAnnule())<span class="tag ko">Annulé</span>@else<span class="num" style="font-size:16px">{{ number_format($paiement->montant, 0, ',', ' ') }}</span>@endif
                    </div>
                @empty
                    <p class="empty">Aucun encaissement pour l'instant.</p>
                @endforelse
            </div>
            <div class="cash-split">
                <div>Espèces à remettre<div class="num">{{ Fcfa::format($resume['especes']) }}</div></div>
                <div>Mobile Money &amp; carte<div class="num">{{ Fcfa::format($resume['electronique']) }}</div></div>
            </div>
            <p class="meta" style="margin:0">Pour annuler une erreur, ouvrez le reçu puis « Annuler ce reçu ».</p>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const URL_CLIENTS = @json(route('caisse.clients'));
    const FRAIS = {{ $fraisInscription }};
    let finDroits = @json($finDroitsPreselectionne?->toDateString());
    let premier = @json($premierAbonnement);
    const $ = (s) => document.querySelector(s);
    const form = document.querySelector('[data-form-abonnement]');
    const fcfa = (n) => new Intl.NumberFormat('fr-FR').format(n).replace(/ | /g, ' ') + ' FCFA';
    const fr = (d) => d.toLocaleDateString('fr-FR');

    // Onglets
    const onglets = [...document.querySelectorAll('.onglets [role="tab"]')];
    onglets.forEach((o) => o.addEventListener('click', () => onglets.forEach((x) => {
        const on = x === o; x.setAttribute('aria-selected', on); document.getElementById(x.getAttribute('aria-controls')).hidden = !on;
    })));

    function recap() {
        const f = form.querySelector('input[name="formule_id"]:checked');
        const m = form.querySelector('input[name="mode"]:checked');
        const frais = FRAIS > 0 && premier && ($('#frais_inscription')?.checked ?? false);
        $('[data-recap-formule]').textContent = f ? f.dataset.nom : '—';
        $('[data-recap-mode]').textContent = m ? m.dataset.libelle : '—';
        $('[data-recap-total]').textContent = f ? fcfa(+f.dataset.prix + (frais ? FRAIS : 0)) : '—';
        $('[data-recap-frais-ligne]').hidden = !frais;
        if (f) {
            const carnet = f.dataset.carnet === '1';
            const debut = !carnet && finDroits ? new Date(finDroits + 'T00:00:00') : new Date();
            if (!carnet && finDroits) debut.setDate(debut.getDate() + 1);
            const fin = new Date(debut); fin.setDate(fin.getDate() + (+f.dataset.duree) - 1);
            $('[data-recap-periode]').textContent = `${fr(debut)} → ${fr(fin)}`;
        }
        $('[data-reference]').hidden = !m || m.value === 'especes';
        const frLabel = document.querySelector('[data-frais]'); if (frLabel) frLabel.hidden = !premier;
    }
    form.addEventListener('change', recap);
    recap();

    document.querySelectorAll('[data-avec-client]').forEach((f) => f.addEventListener('submit', (e) => {
        if (!f.querySelector('[data-client-id]').value) {
            e.preventDefault(); $('[data-erreur-client]').hidden = false;
            $('[data-client-q]').focus(); $('#abonnement').scrollIntoView({ behavior: 'smooth' });
        }
    }));

    // Vente comptoir
    const vente = document.querySelector('[data-form-vente]');
    if (vente) {
        const total = () => {
            let t = 0;
            vente.querySelectorAll('[data-produit]').forEach((p) => {
                const q = +p.querySelector('input').value || 0; t += q * +p.dataset.prix; p.classList.toggle('on', q > 0);
            });
            vente.querySelector('[data-total-vente]').textContent = fcfa(t);
        };
        vente.addEventListener('click', (e) => {
            const b = e.target.closest('[data-plus],[data-moins]'); if (!b) return;
            const i = b.closest('[data-produit]').querySelector('input');
            i.value = Math.max(0, Math.min(+i.max, (+i.value || 0) + (b.matches('[data-plus]') ? 1 : -1))); total();
        });
        vente.addEventListener('input', total);
    }

    // Recherche du client (partagée par les formulaires abonnement et coaching)
    const bloc = document.querySelector('[data-recherche-client]');
    const champ = bloc.querySelector('[data-client-q]');
    const liste = bloc.querySelector('[data-client-resultats]');
    let minuteur = null;

    function choisir(c) {
        document.querySelectorAll('[data-client-id]').forEach((i) => { i.value = c.id; });
        finDroits = c.fin_droits_iso; premier = c.premier;
        $('[data-client-carte]').hidden = false; $('[data-client-vide]').hidden = true; $('[data-erreur-client]').hidden = true;
        $('[data-client-av]').textContent = c.nom.split(' ').map((m) => m[0]).join('').slice(0, 2);
        $('[data-client-nom]').textContent = c.nom;
        $('[data-client-meta]').textContent = [c.type, c.telephone, c.empreinte ? `Empreinte n° ${c.empreinte}` : null].filter(Boolean).join(' · ');
        const tag = $('[data-client-tag]');
        tag.className = 'tag ' + (c.fin_droits ? 'ok' : 'ko');
        tag.textContent = c.fin_droits ? `Droits jusqu’au ${c.fin_droits}` : 'Aucun abonnement en cours';
        $('[data-recap-client]').textContent = c.nom;
        champ.value = ''; liste.hidden = true; recap();
    }

    champ.addEventListener('input', () => {
        clearTimeout(minuteur);
        const q = champ.value.trim();
        if (q.length < 2) { liste.hidden = true; return; }
        minuteur = setTimeout(async () => {
            try {
                const r = await fetch(`${URL_CLIENTS}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                const clients = await r.json();
                liste.replaceChildren();
                if (!clients.length) { const li = document.createElement('li'); li.className = 'empty'; li.style.padding = '10px 12px'; li.textContent = 'Aucun client trouvé'; liste.append(li); }
                clients.forEach((c) => {
                    const li = document.createElement('li'), b = document.createElement('button'), d = document.createElement('small');
                    b.type = 'button'; b.textContent = c.nom;
                    d.textContent = [c.type, c.telephone, c.fin_droits ? `jusqu’au ${c.fin_droits}` : 'sans abonnement en cours'].filter(Boolean).join(' · ');
                    b.append(d); b.addEventListener('click', () => choisir(c)); li.append(b); liste.append(li);
                });
                liste.hidden = false;
            } catch (err) { console.error('Recherche client impossible :', err); liste.hidden = true; }
        }, 250);
    });
    document.addEventListener('click', (e) => { if (!bloc.contains(e.target)) liste.hidden = true; });
})();
</script>
@endpush
