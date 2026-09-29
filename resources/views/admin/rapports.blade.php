@extends('layouts.app')
@section('title', 'Rapports')

@php
    use App\Support\Fcfa;
    $max = max(1, $mois->max('total'));
    $dernier = $mois->last(); $precedent = $mois->slice(-2, 1)->first();
    $evolution = $precedent && $precedent['total'] ? round(100 * ($dernier['total'] - $precedent['total']) / $precedent['total']) : null;
    $heures = range(6, 22);
    $maxCarte = max(0.1, collect($carte)->flatten()->max());
    $jours = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 7 => 'Dim'];
@endphp

@section('content')
<div class="top"><div><div class="eyebrow">Recettes, rétention et affluence : les tendances sur plusieurs mois</div><h1>Rapports</h1></div></div>

<section class="grid g4">
    <div class="card kpi">
        <div class="lbl">Recette de {{ $dernier['mois']->translatedFormat('F') }}</div>
        <div class="num val" style="font-size:40px">{{ Fcfa::format($dernier['total']) }}</div>
        <div class="foot">@if($evolution !== null)<strong style="color:{{ $evolution >= 0 ? 'var(--accent-ink)' : 'var(--danger)' }}">{{ $evolution >= 0 ? '+' : '' }}{{ $evolution }} %</strong> par rapport à {{ $precedent['mois']->translatedFormat('F') }} @else Premier mois @endif</div>
    </div>
    <div class="card kpi">
        <div class="lbl">Échéances des 30 prochains jours</div>
        <div class="num val" style="font-size:40px">{{ $prevu['echeances'] }}</div>
        <div class="foot">Soit {{ Fcfa::format($prevu['potentiel']) }} si tout le monde renouvelle</div>
    </div>
    <div class="card kpi">
        <div class="lbl">Recette attendue (30 j)</div>
        <div class="num val" style="font-size:40px;color:var(--info)">{{ $prevu['prevu'] !== null ? Fcfa::format($prevu['prevu']) : '—' }}</div>
        <div class="foot">{{ $prevu['taux'] !== null ? "Au taux de renouvellement observé ({$prevu['taux']} %)" : 'Pas encore assez d’historique' }}</div>
    </div>
    <div class="card kpi">
        <div class="lbl">Moyenne mensuelle (12 mois)</div>
        <div class="num val" style="font-size:40px">{{ Fcfa::format((int) round($mois->avg('total'))) }}</div>
        <div class="foot">Meilleur mois : {{ $mois->sortByDesc('total')->first()['mois']->translatedFormat('F Y') }}</div>
    </div>
</section>

<section class="card">
    <div class="card-h"><div><h2>Recettes des 12 derniers mois</h2><div class="sub">Abonnements, journaliers, ventes et coaching (reçus annulés exclus)</div></div></div>
    <div class="bars" role="img" aria-label="Recettes mensuelles">
        @foreach($mois as $m)
            <div class="col {{ $loop->last ? 'last' : '' }}" title="{{ $m['mois']->translatedFormat('F Y') }} : {{ Fcfa::format($m['total']) }} (abonnements {{ Fcfa::format($m['abonnements']) }}, journaliers {{ Fcfa::format($m['journaliers']) }}, autres {{ Fcfa::format($m['autres']) }})">
                <span>{{ $m['total'] ? round($m['total'] / 1000).'k' : '' }}</span>
                <i style="height:{{ max(1, round(88 * $m['total'] / $max)) }}%"></i>
            </div>
        @endforeach
    </div>
    <div class="bars-axis" aria-hidden="true">@foreach($mois as $m)<span>{{ ucfirst($m['mois']->translatedFormat('M')) }}</span>@endforeach</div>
</section>

<div class="grid g2" style="align-items:start">
    <section class="card">
        <h2>Rétention par mois d'inscription</h2>
        <p class="sub" style="margin:0">Parmi les nouveaux abonnés de chaque mois, combien ont encore un abonnement aujourd'hui.</p>
        <div class="rows">
            @foreach($cohortes as $c)
                <div class="row">
                    <span style="width:90px;font-weight:700">{{ ucfirst($c['mois']->translatedFormat('F')) }}</span>
                    <div class="grow"><div class="meter" style="height:10px"><i style="width:{{ $c['retention'] ?? 0 }}%"></i></div></div>
                    <span class="meta" style="width:150px;text-align:right">@if($c['inscrits'])<b style="color:var(--fg)">{{ $c['retention'] }} %</b> · {{ $c['restent'] }}/{{ $c['inscrits'] }}@else aucun inscrit @endif</span>
                </div>
            @endforeach
        </div>
    </section>

    <section class="card">
        <h2>Affluence moyenne par heure</h2>
        <p class="sub" style="margin:0">Sur les 4 dernières semaines. Les cases claires sont les heures creuses à remplir (offre « heures creuses », cours).</p>
        <div class="table-wrap" style="border:0">
            <div class="heat" style="min-width:560px">
                <span></span>@foreach($heures as $h)<span>{{ $h }}</span>@endforeach
                @foreach($jours as $n => $j)
                    <span style="text-align:left;font-weight:700">{{ $j }}</span>
                    @foreach($heures as $h)
                        @php($v = $carte[$n][$h])
                        <i title="{{ $j }} {{ $h }} h : {{ $v }} entrée(s) en moyenne" style="background:{{ $v > 0 ? 'rgb(11 122 85 / '.round(0.12 + 0.88 * $v / $maxCarte, 2).')' : 'var(--track)' }}"></i>
                    @endforeach
                @endforeach
            </div>
        </div>
    </section>
</div>

<section class="card">
    <h2>Abonnements en cours par formule</h2>
    @php($maxF = max(1, $formules->max('en_cours')))
    <div class="rows" style="gap:12px">
        @foreach($formules->where('type', '!=', 'coaching') as $f)
            <div class="fbar"><div class="line"><span><strong>{{ $f->nom }}</strong> <span class="muted">· {{ Fcfa::format($f->prix) }}</span></span><span class="num">{{ $f->en_cours }}</span></div><div class="tr"><i style="width:{{ round(100 * $f->en_cours / $maxF) }}%"></i></div></div>
        @endforeach
    </div>
</section>
@endsection
