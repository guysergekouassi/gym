@php use App\Support\Fcfa; @endphp
@extends('layouts.app')

@section('titre', 'Mes KPI')

@section('contenu')
<div class="page-titre">
    <div>
        <h2>@include('partials.icone', ['nom' => 'tableau']) Mes résultats</h2>
        <p>Bonjour {{ auth()->user()->name }} : voici vos ventes personnelles.</p>
    </div>
    <a class="btn btn-primaire" href="{{ route('caisse.index') }}">@include('partials.icone', ['nom' => 'caisse']) Nouvelle vente</a>
</div>

@foreach(['jour' => $jour, 'semaine' => $semaine] as $cle => $kpi)
    @php $r = $kpi['resume']; $prec = $kpi['periode']->libellePrecedente(); @endphp
    <h3 style="margin:8px 0 12px;font-size:17px">{{ $kpi['periode']->libelle() }}</h3>
    <div class="kpis">
        <div class="carte kpi principal-kpi">
            <div class="libelle">Mon chiffre d'affaires</div>
            <div class="valeur">{{ number_format($r['ca'], 0, ',', ' ') }} <small>FCFA</small></div>
            @include('partials.evolution', ['valeur' => $kpi['evolution']['ca'], 'libelle' => $prec])
        </div>
        <div class="carte kpi">
            <div class="icone" style="background:var(--bleu-fond);color:var(--bleu)">@include('partials.icone', ['nom' => 'ticket'])</div>
            <div class="libelle">Mes tickets</div>
            <div class="valeur">{{ $r['tickets'] }}</div>
            @include('partials.evolution', ['valeur' => $kpi['evolution']['tickets'], 'libelle' => $prec])
        </div>
        <div class="carte kpi">
            <div class="icone" style="background:var(--vert-fond);color:var(--vert)">@include('partials.icone', ['nom' => 'panier'])</div>
            <div class="libelle">Mon panier moyen</div>
            <div class="valeur">{{ number_format($r['panier_moyen'], 0, ',', ' ') }} <small>FCFA</small></div>
            @include('partials.evolution', ['valeur' => $kpi['evolution']['panier_moyen'], 'libelle' => $prec])
        </div>
        <div class="carte kpi">
            <div class="icone" style="background:var(--jaune-fond);color:var(--jaune)">@include('partials.icone', ['nom' => 'ciseaux'])</div>
            <div class="libelle">Mes prestations</div>
            <div class="valeur">{{ $r['prestations'] }}</div>
            <span class="evol neutre">{{ $kpi['top_services'][0]['libelle'] ?? 'Aucune vente' }}{{ isset($kpi['top_services'][0]) ? ' : mon n°1' : '' }}</span>
        </div>
    </div>
@endforeach

<div class="grille-dash">
    <div class="carte">
        <div class="carte-titre"><h3>Mon chiffre d'affaires cette semaine</h3><span class="muted small">en FCFA</span></div>
        <div class="carte-corps">@include('partials.barres', ['points' => $semaine['courbe']])</div>
    </div>
    <div class="carte">
        <div class="carte-titre"><h3>Mes services les plus vendus (semaine)</h3></div>
        <div class="carte-corps">
            @php $maxTop = max(1, collect($semaine['top_services'])->max('ca')); @endphp
            <div class="liste-barres">
                @forelse(array_slice($semaine['top_services'], 0, 6) as $i => $s)
                    <div>
                        <div class="ligne-haut"><span><span class="rang {{ $i < 3 ? 'or' : '' }}">{{ $i + 1 }}</span> {{ $s['libelle'] }} <span class="muted small">× {{ $s['quantite'] }}</span></span><b>{{ Fcfa::format($s['ca']) }}</b></div>
                        <div class="jauge"><div style="width:{{ round($s['ca'] * 100 / $maxTop) }}%"></div></div>
                    </div>
                @empty
                    <div class="vide">Pas encore de vente cette semaine.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="carte">
    <div class="carte-titre"><h3>Mes tickets du jour</h3></div>
    @if($tickets->isEmpty())
        <div class="vide">Aucun ticket aujourd'hui.</div>
    @else
        <table class="tableau">
            <thead><tr><th>Ticket</th><th>Heure</th><th>Paiement</th><th class="right">Total</th><th></th></tr></thead>
            <tbody>
            @foreach($tickets as $vente)
                <tr class="{{ $vente->estAnnulee() ? 'annule' : '' }}">
                    <td class="mono"><b>{{ $vente->numero }}</b></td>
                    <td>{{ $vente->created_at->format('H:i') }}</td>
                    <td>{{ $vente->libelleModePaiement() }}</td>
                    <td class="right"><b>{{ Fcfa::format($vente->total) }}</b></td>
                    <td class="right garder">
                        @if($vente->estAnnulee())<span class="badge badge-rouge">Annulé</span>@endif
                        <a class="btn btn-petit" href="{{ route('tickets.show', $vente) }}">Réimprimer</a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
