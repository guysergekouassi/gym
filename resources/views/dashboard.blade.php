@extends('layouts.app')
@section('title', 'Tableau de bord')

@php
    use App\Models\Paiement;
    use App\Models\Passage;
    use App\Support\Fcfa;
    use Illuminate\Support\Carbon;

    $depuis = fn ($date) => $date ? Carbon::parse($date)->diffForHumans() : 'Jamais venu';
    $heures = range(6, 22);
    $maxAffluence = max(1, max($jour['affluence']));
    $heureActuelle = (int) now()->format('G');
    $recetteTotale = $jour['recette_journaliers'] + $jour['recette_abonnements'] + $jour['recette_ventes'];
    $maxMode = max(1, $jour['recette_par_mode']->max() ?? 0);
    $maxFormule = max(1, $formules->max('en_cours') ?? 0);
    $partActifs = $abonnementsEnCours > 0 ? round(100 * $actifs->count() / $abonnementsEnCours) : 0;
    $decides = $renouvellement['renouveles'] + $renouvellement['perdus']->count() + $renouvellement['en_attente']->count();
    $pct = fn ($n) => $decides > 0 ? round(100 * $n / $decides, 1) : 0;
    $periodeJours = (int) $du->diffInDays($au);
    $whatsapp = function (?string $tel) {
        $chiffres = preg_replace('/\D/', '', (string) $tel);
        if ($chiffres === '') {
            return null;
        }
        return 'https://wa.me/'.(strlen($chiffres) === 10 ? '225'.$chiffres : $chiffres);
    };
    $initiales = fn ($c) => $c ? mb_strtoupper(mb_substr($c->nom, 0, 1).mb_substr($c->prenoms ?? '', 0, 1)) : '?';
@endphp

@section('content')
<div class="top">
    <div>
        <div class="eyebrow">{{ ucfirst(now()->translatedFormat('l j F Y · H:i')) }}</div>
        <h1>Tableau de bord</h1>
    </div>
    <form method="GET" class="actions">
        <div class="seg" role="group" aria-label="Période des renouvellements">
            @foreach([7 => '7 jours', 30 => '30 jours', 90 => '90 jours'] as $n => $libelle)
                <a href="{{ route('dashboard', ['du' => today()->subDays($n)->toDateString()]) }}"
                   aria-current="{{ $periodeJours === $n && $au->isToday() ? 'true' : 'false' }}">{{ $libelle }}</a>
            @endforeach
        </div>
        @if($salles->count() > 1)
            <label class="sr" for="salle">Salle</label>
            <select id="salle" name="salle" class="btn ghost sm" onchange="this.form.submit()">
                <option value="">Toutes les salles</option>
                @foreach($salles as $s)<option value="{{ $s->id }}" @selected($salleId === $s->id)>{{ $s->nom }}</option>@endforeach
            </select>
        @endif
        <label class="sr" for="du">Du</label><input id="du" type="date" name="du" value="{{ $du->toDateString() }}" class="btn ghost sm">
        <label class="sr" for="au">Au</label><input id="au" type="date" name="au" value="{{ $au->toDateString() }}" class="btn ghost sm">
        <button class="btn sm">Filtrer</button>
    </form>
</div>

@if($messagesEnAttente || $alertesStock->isNotEmpty() || $prospectsOuverts)
    <section class="actions" aria-label="À traiter">
        @if($messagesEnAttente)<a href="{{ route('taches.index') }}" class="tag warn" style="font-size:13px;padding:8px 12px">@include('partials.icone', ['nom' => 'whatsapp', 'taille' => 14]) {{ $messagesEnAttente }} message(s) à envoyer</a>@endif
        @if($prospectsOuverts)<a href="{{ route('prospects.index') }}" class="tag info" style="font-size:13px;padding:8px 12px">{{ $prospectsOuverts }} prospect(s) à suivre</a>@endif
        @foreach($alertesStock as $p)<a href="{{ route('admin.produits.index') }}" class="tag ko" style="font-size:13px;padding:8px 12px">Stock bas : {{ $p->nom }} ({{ $p->stock }})</a>@endforeach
        @if($prevu['prevu'] !== null)<span class="tag ok" style="font-size:13px;padding:8px 12px">Recette attendue sur 30 j : {{ Fcfa::format($prevu['prevu']) }}</span>@endif
    </section>
@endif

<section class="grid g4" aria-label="Indicateurs clés">
    <div class="card kpi">
        <div class="lbl">Abonnements en cours</div>
        <div class="num val">{{ $abonnementsEnCours }}</div>
        <div class="foot">{{ $jour['journaliers'] }} entrée(s) journalière(s) aujourd'hui</div>
    </div>
    <div class="card kpi">
        <div class="lbl">Abonnés actifs · {{ config('salle.kpi.actif_jours') }} j</div>
        <div style="display:flex;align-items:baseline;gap:10px"><div class="num val" style="color:var(--accent-ink)">{{ $actifs->count() }}</div><strong style="color:var(--accent-ink)">{{ $partActifs }} %</strong></div>
        <div class="meter"><i style="width:{{ $partActifs }}%"></i></div>
    </div>
    <div class="card kpi">
        <div class="lbl">Moins actifs · {{ config('salle.kpi.inactif_jours') }} j sans venir</div>
        <div class="num val" style="color:var(--warn-ink)">{{ $moinsActifs->count() }}</div>
        <div class="foot">À relancer · <a href="#relancer">voir la liste</a></div>
    </div>
    <div class="card kpi">
        <div class="lbl">Taux de renouvellement</div>
        <div class="num val" style="color:var(--info)">{{ $renouvellement['taux'] !== null ? $renouvellement['taux'].' %' : '—' }}</div>
        <div class="foot">{{ $renouvellement['renouveles'] }} / {{ $renouvellement['echus'] }} échus · {{ $renouvellement['en_attente']->count() }} en délai de grâce</div>
    </div>
</section>

<section class="grid g3">
    <div class="card span2">
        <div class="card-h">
            <div><h2>Affluence d'aujourd'hui</h2><div class="sub">Entrées autorisées par heure : empreinte et journaliers</div></div>
            <div class="stats">
                <div><div class="num" style="font-size:30px">{{ $jour['entrees'] }}</div><small>entrées</small></div>
                <div><div class="num" style="font-size:30px;color:var(--danger)">{{ $jour['refus'] }}</div><small>accès refusés</small></div>
                <div><div class="num" style="font-size:30px">{{ $jour['heure_pointe'] !== null ? $jour['heure_pointe'].' h' : '—' }}</div><small>heure de pointe</small></div>
            </div>
        </div>
        <div class="chart" role="img" aria-label="Entrées par heure de 6 h à 22 h">
            @foreach($heures as $h)
                @php($n = $jour['affluence'][$h])
                <div class="col {{ $h > $heureActuelle ? 'future' : ($n > 0 && $n === $maxAffluence ? 'peak' : '') }}" title="{{ $h }} h : {{ $n }} entrée(s)">
                    <span>{{ $n ?: '' }}</span><i style="height:{{ max(2, round(84 * $n / $maxAffluence)) }}%"></i>
                </div>
            @endforeach
        </div>
        <div class="axis" aria-hidden="true">@foreach($heures as $h)<span>{{ $h }}h</span>@endforeach</div>
    </div>

    <div class="card">
        <div class="card-h"><h2>Derniers passages</h2><a href="{{ route('passages.index') }}" class="live"><span class="dot"></span>Tout voir</a></div>
        <div class="rows">
            @forelse($derniersPassages as $p)
                <div class="row">
                    <div class="av {{ $p->estAutorise() ? '' : 'ko' }}">{{ $p->methode === Passage::METHODE_CAISSE && ! $p->client ? 'J' : $initiales($p->client) }}</div>
                    <div class="grow">
                        <div class="name">{{ $p->client?->nom_complet ?? ($p->methode === Passage::METHODE_CAISSE ? 'Journalier' : $p->identification()) }}</div>
                        <div class="meta {{ $p->estAutorise() ? '' : 'ko' }}">{{ $p->estAutorise() ? (Passage::METHODES[$p->methode] ?? $p->methode) : 'Refusé · '.$p->message() }}</div>
                    </div>
                    <span class="time">{{ $p->passe_le->format('H:i') }}</span>
                </div>
            @empty
                <p class="empty">Aucun passage enregistré.</p>
            @endforelse
        </div>
    </div>
</section>

<section class="grid g3">
    <div class="card dark">
        <h2>Recette du jour</h2>
        <div class="num big">{{ number_format($recetteTotale, 0, ',', ' ') }} <small>FCFA</small></div>
        <div class="split">
            <div><span class="muted">Abonnements</span><div class="num">{{ number_format($jour['recette_abonnements'], 0, ',', ' ') }}</div></div>
            <div><span class="muted">Journaliers</span><div class="num">{{ number_format($jour['recette_journaliers'], 0, ',', ' ') }}</div></div>
        </div>
        @if($jour['recette_ventes'])<div class="line"><span>Bar, boutique et coaching</span><span class="num">{{ number_format($jour['recette_ventes'], 0, ',', ' ') }}</span></div>@endif
        @if($jour['recette_par_mode']->isNotEmpty())
            <div class="cap">Par mode de paiement</div>
            @foreach($jour['recette_par_mode'] as $mode => $montant)
                <div class="hbar"><span>{{ Paiement::MODES[$mode] ?? $mode }}</span><div class="tr"><i style="width:{{ round(100 * $montant / $maxMode) }}%"></i></div><span class="num">{{ number_format($montant, 0, ',', ' ') }}</span></div>
            @endforeach
        @endif
        <div class="cap">Par caisse</div>
        @forelse($jour['recette_par_caisse'] as $ligne)
            <div class="line"><span>{{ $ligne['nom'] }} · {{ $ligne['nombre'] }} reçu(s)</span><span class="num">{{ number_format($ligne['total'], 0, ',', ' ') }}</span></div>
        @empty
            <p class="empty muted">Aucun encaissement aujourd'hui.</p>
        @endforelse
        <a href="{{ route('admin.caisses.index') }}" style="color:var(--live)">Détail des caisses →</a>
    </div>

    <div class="card" id="relancer">
        <div class="card-h">
            <div><h2>À relancer</h2><div class="sub">Abonnés sans venue depuis {{ config('salle.kpi.inactif_jours') }} jours</div></div>
            <span class="tag warn">{{ $moinsActifs->count() }}</span>
        </div>
        <div class="rows">
            @forelse($moinsActifs->take(6) as $client)
                <div class="row">
                    <div class="grow">
                        <a class="name" href="{{ route('clients.show', $client) }}">{{ $client->nom_complet }}</a>
                        <div class="meta">{{ $client->telephone ?? 'Pas de téléphone' }}</div>
                    </div>
                    <span class="tag warn">{{ $depuis($client->dernier_passage_le) }}</span>
                    @if($lien = $whatsapp($client->telephone))
                        <a href="{{ $lien }}" target="_blank" rel="noopener" class="icon-btn" aria-label="Écrire à {{ $client->nom_complet }} sur WhatsApp">@include('partials.icone', ['nom' => 'message', 'taille' => 17])</a>
                    @endif
                </div>
            @empty
                <p class="empty">Aucun abonné inactif.</p>
            @endforelse
        </div>
    </div>

    <div class="card">
        <div class="card-h">
            <div><h2>Expirent bientôt</h2><div class="sub">Dans les {{ config('salle.kpi.expiration_alerte_jours') }} jours, pas encore renouvelés</div></div>
            <span class="tag info">{{ $expirantBientot->count() }}</span>
        </div>
        <div class="rows">
            @forelse($expirantBientot->take(6) as $abonnement)
                @php($j = $abonnement->joursRestants())
                <div class="row">
                    <div class="days {{ $j <= 2 ? 'urgent' : '' }}"><b>{{ $j }}</b><small>{{ $j > 1 ? 'jours' : 'jour' }}</small></div>
                    <div class="grow">
                        <a class="name" href="{{ route('clients.show', $abonnement->client) }}">{{ $abonnement->client->nom_complet }}</a>
                        <div class="meta">{{ $abonnement->formule->nom }} · fin le {{ $abonnement->date_fin->format('d/m') }} · {{ $abonnement->client->telephone ?? 'pas de téléphone' }}</div>
                    </div>
                </div>
            @empty
                <p class="empty">Aucune échéance proche.</p>
            @endforelse
        </div>
    </div>
</section>

<section class="grid g3">
    <div class="card">
        <h2>Renouvellements · {{ $du->format('d/m') }} – {{ $au->format('d/m') }}</h2>
        <div class="stack" role="img" aria-label="{{ $renouvellement['renouveles'] }} renouvelés, {{ $renouvellement['en_attente']->count() }} en délai de grâce, {{ $renouvellement['perdus']->count() }} non renouvelés">
            <i style="width:{{ $pct($renouvellement['renouveles']) }}%;background:var(--info)"></i>
            <i style="width:{{ $pct($renouvellement['en_attente']->count()) }}%;background:var(--info-2)"></i>
            <i style="width:{{ $pct($renouvellement['perdus']->count()) }}%;background:var(--neutral-bar)"></i>
        </div>
        <div class="legend">
            <div><i style="background:var(--info)"></i><span>Renouvelés</span><b class="num">{{ $renouvellement['renouveles'] }}</b></div>
            <div><i style="background:var(--info-2)"></i><span>En délai de grâce ({{ config('salle.kpi.renouvellement_delai_jours') }} j)</span><b class="num">{{ $renouvellement['en_attente']->count() }}</b></div>
            <div><i style="background:var(--neutral-bar)"></i><span>Non renouvelés</span><b class="num">{{ $renouvellement['perdus']->count() }}</b></div>
        </div>
        @if($renouvellement['perdus']->isNotEmpty())
            <div class="rows">
                @foreach($renouvellement['perdus']->take(5) as $abonnement)
                    <div class="row">
                        <div class="grow"><a class="name" href="{{ route('clients.show', $abonnement->client) }}">{{ $abonnement->client->nom_complet }}</a>
                        <div class="meta">Fin le {{ $abonnement->date_fin->format('d/m/Y') }} · {{ $abonnement->client->telephone ?? 'pas de téléphone' }}</div></div>
                    </div>
                @endforeach
            </div>
        @endif
        @if($renouvellements->isNotEmpty())
            <div class="sub">{{ $renouvellements->count() }} client(s) ont renouvelé sur la période.</div>
        @endif
    </div>

    <div class="card">
        <h2>Les plus assidus · 30 j</h2>
        <div class="rows">
            @forelse($actifs->take(6) as $i => $client)
                <div class="row">
                    <span class="num" style="width:20px;font-size:19px;color:var(--muted)">{{ $i + 1 }}</span>
                    <a class="grow name" href="{{ route('clients.show', $client) }}">{{ $client->nom_complet }}</a>
                    <span class="meta"><b class="num" style="font-size:19px;color:var(--fg)">{{ $client->passages_30j }}</b> venues</span>
                </div>
            @empty
                <p class="empty">Aucun abonné actif sur la période.</p>
            @endforelse
        </div>
    </div>

    <div class="card">
        <h2>Abonnements en cours par formule</h2>
        <div class="rows" style="gap:12px">
            @foreach($formules as $formule)
                <div class="fbar">
                    <div class="line"><span><strong>{{ $formule->nom }}</strong> <span class="muted">· {{ Fcfa::format($formule->prix) }}</span></span><span class="num">{{ $formule->en_cours }}</span></div>
                    <div class="tr"><i style="width:{{ round(100 * $formule->en_cours / $maxFormule) }}%"></i></div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endsection
