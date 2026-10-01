@php
    use App\Support\Fcfa;
    $r = $kpi['resume'];
    $prec = $kpi['periode']->libellePrecedente();
    $maxTop = max(1, collect($kpi['top_services'])->max('ca'));
    $maxPers = max(1, collect($kpi['par_personne'])->max('ca'));
@endphp
@extends('layouts.app')

@section('titre', 'Tableau de bord')

@section('contenu')
<div class="page-titre">
    <div>
        <h2>@include('partials.icone', ['nom' => 'tableau']) États de vente</h2>
        <p>{{ $kpi['periode']->libelle() }}</p>
    </div>
    @include('partials.selecteur-periode', ['periode' => $kpi['periode']])
</div>

<div class="kpis">
    <div class="carte kpi principal-kpi">
        <div class="libelle">Chiffre d'affaires</div>
        <div class="valeur">{{ number_format($r['ca'], 0, ',', ' ') }} <small>FCFA</small></div>
        @include('partials.evolution', ['valeur' => $kpi['evolution']['ca'], 'libelle' => $prec])
    </div>
    <div class="carte kpi">
        <div class="icone" style="background:var(--bleu-fond);color:var(--bleu)">@include('partials.icone', ['nom' => 'ticket'])</div>
        <div class="libelle">Tickets encaissés</div>
        <div class="valeur">{{ $r['tickets'] }}</div>
        @include('partials.evolution', ['valeur' => $kpi['evolution']['tickets'], 'libelle' => $prec])
    </div>
    <div class="carte kpi">
        <div class="icone" style="background:var(--vert-fond);color:var(--vert)">@include('partials.icone', ['nom' => 'panier'])</div>
        <div class="libelle">Panier moyen</div>
        <div class="valeur">{{ number_format($r['panier_moyen'], 0, ',', ' ') }} <small>FCFA</small></div>
        @include('partials.evolution', ['valeur' => $kpi['evolution']['panier_moyen'], 'libelle' => $prec])
    </div>
    <div class="carte kpi">
        <div class="icone" style="background:var(--jaune-fond);color:var(--jaune)">@include('partials.icone', ['nom' => 'ciseaux'])</div>
        <div class="libelle">Prestations réalisées</div>
        <div class="valeur">{{ $r['prestations'] }}</div>
        <span class="evol neutre">
            @if($kpi['annulations']['nombre'])
                {{ $kpi['annulations']['nombre'] }} ticket(s) annulé(s) · {{ Fcfa::format($kpi['annulations']['montant']) }}
            @else
                Aucun ticket annulé
            @endif
        </span>
    </div>
</div>

<div class="grille-dash">
    <div class="carte">
        <div class="carte-titre">
            <h3>{{ $kpi['periode']->nombreJours() === 1 ? "Chiffre d'affaires par heure" : "Chiffre d'affaires par jour" }}</h3>
            <span class="muted small">en FCFA</span>
        </div>
        <div class="carte-corps">@include('partials.barres', ['points' => $kpi['courbe']])</div>
    </div>
    <div class="carte">
        <div class="carte-titre"><h3>Par catégorie</h3></div>
        <div class="carte-corps">
            @forelse($kpi['categories'] as $c)
                <div class="liste-barres" style="margin-bottom:14px">
                    <div>
                        <div class="ligne-haut"><span><span class="pastille" style="background:{{ $c['couleur'] }}"></span>{{ $c['libelle'] }}</span><b>{{ Fcfa::format($c['ca']) }} · {{ $c['part'] }} %</b></div>
                        <div class="jauge"><div style="width:{{ $c['part'] }}%;background:{{ $c['couleur'] }}"></div></div>
                    </div>
                </div>
            @empty
                <div class="vide">Aucune vente sur la période.</div>
            @endforelse

            <h3 style="font-size:15px;margin:22px 0 12px">Modes de paiement</h3>
            @forelse($kpi['modes_paiement'] as $m)
                <div class="liste-barres" style="margin-bottom:12px">
                    <div>
                        <div class="ligne-haut"><span>{{ $m['libelle'] }} <span class="muted small">({{ $m['tickets'] }})</span></span><b>{{ Fcfa::format($m['ca']) }}</b></div>
                        <div class="jauge"><div style="width:{{ $m['part'] }}%;background:var(--vert)"></div></div>
                    </div>
                </div>
            @empty
                <div class="muted small">—</div>
            @endforelse
        </div>
    </div>
</div>

<div class="grille-3">
    <div class="carte">
        <div class="carte-titre"><h3>@include('partials.icone', ['nom' => 'etoile', 'style' => 'color:var(--jaune)']) Top services</h3></div>
        <div class="carte-corps">
            <div class="liste-barres">
                @forelse($kpi['top_services'] as $i => $s)
                    <div>
                        <div class="ligne-haut">
                            <span><span class="rang {{ $i < 3 ? 'or' : '' }}">{{ $i + 1 }}</span> {{ $s['libelle'] }} <span class="muted small">× {{ $s['quantite'] }}</span></span>
                            <b>{{ Fcfa::format($s['ca']) }}</b>
                        </div>
                        <div class="jauge"><div style="width:{{ round($s['ca'] * 100 / $maxTop) }}%"></div></div>
                    </div>
                @empty
                    <div class="vide">Aucune vente sur la période.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="carte">
        <div class="carte-titre"><h3>@include('partials.icone', ['nom' => 'utilisateurs']) Ventes par personne</h3></div>
        <div class="carte-corps">
            <div class="liste-barres">
                @forelse($kpi['par_personne'] as $p)
                    <div>
                        <div class="ligne-haut">
                            <span><b>{{ $p['nom'] }}</b> <span class="muted small">{{ $p['tickets'] }} ticket(s) · panier {{ Fcfa::format($p['panier_moyen']) }}</span></span>
                            <b>{{ Fcfa::format($p['ca']) }}</b>
                        </div>
                        <div class="jauge"><div style="width:{{ round($p['ca'] * 100 / $maxPers) }}%;background:var(--bleu)"></div></div>
                    </div>
                @empty
                    <div class="vide">Aucune vente sur la période.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="carte">
        <div class="carte-titre"><h3>@include('partials.icone', ['nom' => 'horloge']) Heures d'affluence</h3><span class="muted small">tickets par heure</span></div>
        <div class="carte-corps">
            @include('partials.barres', ['points' => $kpi['heures'], 'format' => 'nombre', 'classe' => 'compact'])
            @if($kpi['periode']->nombreJours() >= 7)
                <h3 style="font-size:15px;margin:18px 0 0">Jours les plus rentables</h3>
                @include('partials.barres', ['points' => $kpi['jours_semaine'], 'classe' => 'compact'])
            @endif
        </div>
    </div>
</div>

<div class="carte">
    <div class="carte-titre">
        <h3>Derniers tickets</h3>
        <a class="btn btn-petit" href="{{ route('ventes.index', request()->only('periode', 'du', 'au')) }}">Tout l'historique →</a>
    </div>
    @include('ventes.tableau', ['ventes' => $derniers])
</div>
@endsection
