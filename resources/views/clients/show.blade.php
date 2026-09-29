@extends('layouts.app')
@section('title', $client->nom_complet)

@php
    use App\Models\Client;
    use App\Support\Fcfa;
    $user = auth()->user();
    $jours = $finDroits ? (int) today()->diffInDays($finDroits, false) : null;
    $venues = $jours14->where('venu', true)->count();
    $poids = $client->mesures->whereNotNull('poids')->values();
@endphp

@section('content')
<div class="eyebrow"><a href="{{ route('clients.index') }}">← Clients</a></div>

<section class="card">
    <div class="profile">
        <div class="av">@if($client->photo_url)<img src="{{ $client->photo_url }}" alt="">@else{{ $client->initiales }}@endif</div>
        <div class="grow">
            <h1>{{ $client->nom_complet }}</h1>
            <div class="meta" style="font-size:14px">
                {{ Client::TYPES[$client->type] ?? $client->type }} · {{ $client->telephone ?? 'pas de téléphone' }}
                · Empreinte {{ $client->empreinte_id ? 'n° '.$client->empreinte_id : 'non enrôlée' }}
                · Carte {{ $client->carte_id ?: 'aucune' }}
                · membre depuis {{ $client->created_at->translatedFormat('F Y') }}
            </div>
            <div class="pills">
                @if($gelEnCours)
                    <span class="tag gel">Gelé jusqu'au {{ $gelEnCours->au->format('d/m/Y') }}</span>
                @elseif($finDroits)
                    <span class="tag {{ $jours <= (int) config('salle.kpi.expiration_alerte_jours') ? 'warn' : 'ok' }}">{{ $jours === 0 ? 'Expire aujourd’hui' : "Droits jusqu'au {$finDroits->format('d/m/Y')} · {$jours} j" }}</span>
                @elseif($abonnementActif?->estCarnet())
                    <span class="tag ok">Carnet : {{ $abonnementActif->entrees_restantes }} entrée(s) restante(s)</span>
                @elseif($client->type === Client::TYPE_ABONNE)
                    <span class="tag ko">Abonnement expiré</span>
                @endif
                @if($client->parrain)<span class="tag info">Parrainé par {{ $client->parrain->nom_complet }}</span>@endif
                @if($client->filleuls->isNotEmpty())<span class="tag info">{{ $client->filleuls->count() }} filleul(s)</span>@endif
            </div>
        </div>
        <div class="actions">
            @if($user->isCaissier())
                <a href="{{ route('caisse.index', ['client_id' => $client->id]) }}#abonnement" class="btn">{{ $finDroits ? 'Prolonger' : 'Abonner / renouveler' }}</a>
            @endif
            <a href="{{ route('clients.edit', $client) }}" class="btn ghost">Modifier</a>
            <form method="POST" action="{{ route('clients.lien-membre', $client) }}" data-confirmer="Créer un nouveau lien d'espace membre ?" data-texte="L'ancien lien de {{ $client->appel }} ne fonctionnera plus. Vous pourrez ensuite l'envoyer sur WhatsApp." data-bouton="Créer le lien">
                @csrf
                <button class="btn ghost" @disabled(! $client->telephone) title="{{ $client->telephone ? '' : 'Ajoutez un téléphone pour envoyer le lien' }}">@include('partials.icone', ['nom' => 'whatsapp', 'taille' => 16])Lien espace membre</button>
            </form>
            @if($user->isAdmin())
                <form method="POST" action="{{ route('clients.destroy', $client) }}" data-confirmer="Archiver {{ $client->nom_complet }} ?" data-texte="Le client disparaît des listes, mais son historique (paiements, passages) est conservé." data-bouton="Archiver" data-variante="danger">
                    @csrf @method('DELETE')
                    <button class="btn ghost" style="color:var(--danger);border-color:var(--danger)">Archiver</button>
                </form>
            @endif
        </div>
    </div>

    @if($lien = session('lien_membre'))
        <div class="hint-card" style="display:flex;flex-direction:column;gap:10px">
            <strong style="color:var(--fg)">Nouveau lien personnel créé. Les anciens liens ne fonctionnent plus.</strong>
            <div class="copy-box"><span data-lien>{{ $lien['url'] }}</span><button type="button" class="btn sm ghost" data-copier>Copier</button></div>
            @if($lien['whatsapp'])<a href="{{ $lien['whatsapp'] }}" target="_blank" rel="noopener" class="btn sm" style="align-self:flex-start;background:#1E8E57">Envoyer sur WhatsApp</a>@endif
        </div>
    @endif
</section>

<div class="grid g3">
    <div class="card">
        <div class="lbl meta" style="font-weight:700">Venues sur les 14 derniers jours</div>
        <div class="dots" role="img" aria-label="{{ $venues }} jours de venue sur 14">
            @foreach($jours14 as $j)<i class="{{ $j['venu'] ? 'on' : '' }}" title="{{ $j['date']->translatedFormat('D j M') }}"></i>@endforeach
        </div>
        <div class="meta"><b class="num" style="font-size:22px;color:var(--fg)">{{ $venues }} / 14</b>@if($habitude !== null) · vient surtout vers {{ $habitude }} h @endif</div>
    </div>
    <div class="card">
        <div class="meta" style="font-weight:700">Dépensé depuis l'inscription</div>
        <div class="num" style="font-size:34px">{{ Fcfa::format($depense) }}</div>
        <div class="meta">{{ $client->abonnements->where('statut', 'actif')->count() }} abonnement(s) · {{ $client->packs->count() }} pack(s) coaching</div>
    </div>
    <div class="card">
        <div class="meta" style="font-weight:700">Risque de départ</div>
        <div><span class="tag {{ $risque['classe'] }}" style="font-size:16px;padding:6px 14px">{{ $risque['niveau'] }}</span></div>
        <div class="meta">{{ $risque['raison'] }}</div>
    </div>
</div>

<div class="grid g2" style="align-items:start">
    <div class="grid">
        <details class="panel" @if($gelEnCours || $errors->has('du')) open @endif>
            <summary>@include('partials.icone', ['nom' => 'flocon', 'taille' => 16]) Geler l'abonnement</summary>
            <div class="inner">
                <p class="meta" style="margin:0">Voyage, maladie… Pendant le gel l'accès est refusé, et les jours gelés sont ajoutés à la fin de l'abonnement.</p>
                <form method="POST" action="{{ route('clients.gels.store', $client) }}" class="inline-form" data-confirmer="Geler l'abonnement ?" data-texte="Pendant le gel, l'accès à la salle sera refusé. Les jours gelés sont ajoutés à la fin de l'abonnement." data-bouton="Geler">
                    @csrf
                    <label class="fld sm" for="du">À partir du<input id="du" type="date" name="du" value="{{ old('du', today()->toDateString()) }}" min="{{ today()->toDateString() }}" required></label>
                    <label class="fld sm" for="jours">Nombre de jours<input id="jours" type="number" name="jours" min="1" max="180" value="{{ old('jours', 15) }}" required></label>
                    <label class="fld sm" for="motif-gel" style="flex-basis:100%">Motif<input id="motif-gel" type="text" name="motif" maxlength="255" placeholder="ex. voyage à Bouaké"></label>
                    <button class="btn sm">Geler</button>
                </form>
                @foreach($client->gels as $gel)
                    <div class="row">
                        <div class="grow"><span class="name">{{ $gel->du->format('d/m/Y') }} → {{ $gel->au->format('d/m/Y') }} · {{ $gel->jours }} j</span><div class="meta">{{ $gel->motif ?: 'Sans motif' }}</div></div>
                        @if(today()->lte($gel->au))
                            <form method="POST" action="{{ route('gels.terminer', $gel) }}" data-confirmer="{{ today()->lt($gel->du) ? 'Annuler ce gel ?' : 'Terminer le gel maintenant ?' }}" data-texte="Le membre pourra de nouveau entrer. Les jours non utilisés sont retirés de la prolongation." data-bouton="Confirmer">@csrf<button class="pill-btn">{{ today()->lt($gel->du) ? 'Annuler' : 'Terminer maintenant' }}</button></form>
                        @else
                            <span class="tag off">Terminé</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </details>

        @if($client->packs->isNotEmpty())
            <section class="card">
                <h2>Coaching personnel</h2>
                @foreach($client->packs as $pack)
                    <div class="row">
                        <div class="grow">
                            <span class="name">{{ $pack->formule?->nom ?? 'Pack coaching' }}</span>
                            <div class="meta">{{ $pack->seances_restantes }} / {{ $pack->seances_total }} séance(s) restante(s) · {{ $pack->coach?->nom ?? 'coach à définir' }} · jusqu'au {{ $pack->expire_le?->format('d/m/Y') }}</div>
                        </div>
                        @if($pack->statut === 'actif')
                            <form method="POST" action="{{ route('packs.seance', $pack) }}" class="inline-form">
                                @csrf
                                <select name="coach_id" aria-label="Coach de la séance" class="btn ghost sm">
                                    @foreach($coachs as $coach)<option value="{{ $coach->id }}" @selected($coach->id === $pack->coach_id)>{{ $coach->nom }}</option>@endforeach
                                </select>
                                <button class="pill-btn">Séance faite</button>
                            </form>
                        @else
                            <span class="tag off">{{ $pack->statut === 'annule' ? 'Annulé' : 'Terminé' }}</span>
                        @endif
                    </div>
                @endforeach
            </section>
        @endif

        <section class="card">
            <h2>Abonnements</h2>
            <div class="rows">
                @forelse($client->abonnements as $abonnement)
                    <div class="row">
                        <div class="grow">
                            <span class="name" @if($abonnement->statut === 'annule') style="text-decoration:line-through" @endif>{{ $abonnement->formule->nom }}</span>
                            @if($abonnement->est_renouvellement)<span class="tag info">Renouvellement</span>@endif
                            @if($abonnement->statut === 'annule')<span class="tag ko">Annulé</span>@endif
                            <div class="meta">{{ $abonnement->date_debut->format('d/m/Y') }} → {{ $abonnement->date_fin->format('d/m/Y') }}@if($abonnement->estCarnet()) · {{ $abonnement->entrees_restantes }} entrée(s) restante(s)@endif @if($abonnement->remise) · remise {{ Fcfa::format($abonnement->remise) }}@endif</div>
                        </div>
                        <span class="num" style="font-size:16px">{{ Fcfa::format($abonnement->montant) }}</span>
                    </div>
                @empty
                    <p class="empty">Aucun abonnement.</p>
                @endforelse
            </div>
        </section>

        <section class="card">
            <div class="card-h"><h2>Suivi et progression</h2>@if($poids->count() > 1)<span class="tag {{ $poids->last()->poids <= $poids->first()->poids ? 'ok' : 'warn' }}">{{ ($poids->last()->poids - $poids->first()->poids) > 0 ? '+' : '' }}{{ round($poids->last()->poids - $poids->first()->poids, 1) }} kg</span>@endif</div>
            @if($poids->count() > 1)
                @php
                    $min = $poids->min('poids') - 1; $max = $poids->max('poids') + 1; $n = $poids->count();
                    $pts = $poids->values()->map(fn ($m, $i) => [round(20 + $i * 360 / max(1, $n - 1), 1), round(70 - ($m->poids - $min) * 55 / max(0.1, $max - $min), 1)]);
                    $ligne = $pts->map(fn ($p, $i) => ($i ? 'L' : 'M').$p[0].' '.$p[1])->implode(' ');
                @endphp
                <svg class="spark" viewBox="0 0 400 90" role="img" aria-label="Évolution du poids">
                    <path class="a" d="{{ $ligne }} L{{ $pts->last()[0] }} 80 L{{ $pts->first()[0] }} 80 Z"></path>
                    <path class="l" d="{{ $ligne }}"></path>
                    @foreach($pts as $i => $p)<circle cx="{{ $p[0] }}" cy="{{ $p[1] }}" r="3.5"></circle>@endforeach
                    <text x="20" y="88">{{ $poids->first()->date->format('d/m') }} · {{ $poids->first()->poids }} kg</text>
                    <text x="380" y="88" text-anchor="end">{{ $poids->last()->date->format('d/m') }} · {{ $poids->last()->poids }} kg</text>
                </svg>
            @endif
            @if($client->mesures->isNotEmpty())
                <div class="table-wrap"><table style="min-width:0">
                    <thead><tr><th>Date</th><th class="r">Poids</th><th class="r">Tour de taille</th><th class="r">Masse grasse</th><th class="r">IMC</th></tr></thead>
                    <tbody>@foreach($client->mesures->sortByDesc('date')->take(6) as $m)
                        <tr><td>{{ $m->date->format('d/m/Y') }}</td><td class="r">{{ $m->poids ? $m->poids.' kg' : '—' }}</td><td class="r">{{ $m->tour_taille ? $m->tour_taille.' cm' : '—' }}</td><td class="r">{{ $m->masse_grasse ? $m->masse_grasse.' %' : '—' }}</td><td class="r">{{ $m->imc() ?? '—' }}</td></tr>
                    @endforeach</tbody>
                </table></div>
            @endif
            <details class="panel">
                <summary>Ajouter une mesure</summary>
                <form method="POST" action="{{ route('clients.mesures.store', $client) }}" class="inner inline-form">
                    @csrf
                    <label class="fld sm" for="m-date">Date<input id="m-date" type="date" name="date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></label>
                    <label class="fld sm" for="m-poids">Poids (kg)<input id="m-poids" type="number" step="0.1" name="poids"></label>
                    <label class="fld sm" for="m-taille">Taille (cm)<input id="m-taille" type="number" name="taille" value="{{ $client->mesures->last()?->taille }}"></label>
                    <label class="fld sm" for="m-tt">Tour de taille (cm)<input id="m-tt" type="number" step="0.1" name="tour_taille"></label>
                    <label class="fld sm" for="m-mg">Masse grasse (%)<input id="m-mg" type="number" step="0.1" name="masse_grasse"></label>
                    <button class="btn sm">Enregistrer</button>
                </form>
            </details>
            <details class="panel" @if($client->objectif || $client->programme) open @endif>
                <summary>Objectif et programme</summary>
                <form method="POST" action="{{ route('clients.suivi', $client) }}" class="inner">
                    @csrf
                    <label class="fld sm" for="objectif">Objectif<input id="objectif" type="text" name="objectif" maxlength="255" value="{{ $client->objectif }}" placeholder="ex. perdre 5 kg avant décembre"></label>
                    <label class="fld" for="programme">Programme d'entraînement (visible dans l'espace membre)<textarea id="programme" name="programme" rows="5" placeholder="Lundi : haut du corps…">{{ $client->programme }}</textarea></label>
                    <button class="btn sm" style="align-self:flex-start">Enregistrer</button>
                </form>
            </details>
        </section>
    </div>

    <section class="card">
        <h2>Historique</h2>
        @if($client->notes)<div class="hint-card"><strong style="color:var(--fg)">Note :</strong> {{ $client->notes }}</div>@endif
        <div class="timeline">
            @forelse($timeline as $ev)
                <div class="ev {{ $ev['classe'] }}">
                    <b>@if(isset($ev['recu']))<a href="{{ route('recus.show', $ev['recu']) }}" style="color:inherit">{{ $ev['titre'] }}</a>@else{{ $ev['titre'] }}@endif</b>
                    <span class="meta">{{ $ev['date']->format('d/m/Y H:i') }}{{ $ev['detail'] ? ' · '.$ev['detail'] : '' }}</span>
                </div>
            @empty
                <p class="empty">Rien pour l'instant.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
document.querySelector('[data-copier]')?.addEventListener('click', async (e) => {
    const texte = document.querySelector('[data-lien]').textContent;
    try { await navigator.clipboard.writeText(texte); e.target.textContent = 'Copié'; window.gfToast?.('Lien copié'); }
    catch { const r = document.createRange(); r.selectNodeContents(document.querySelector('[data-lien]')); getSelection().removeAllRanges(); getSelection().addRange(r); }
});
</script>
@endpush
