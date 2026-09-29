@extends('layouts.app')
@section('title', 'Passages')

@php
    use App\Http\Controllers\PassageController;
    use App\Models\Client;
    use App\Models\Passage;
    $estAujourdhui = $jour->isToday();
    $lien = fn (array $params) => route('passages.index', array_filter(
        array_merge(['date' => $estAujourdhui ? null : $jour->toDateString(), 'filtre' => $filtre, 'vue' => $vue, 'q' => $q], $params),
        fn ($v) => $v !== null && $v !== '' && $v !== 'tous' && $v !== 'passage'
    ));
    $initiales = fn (?Client $c) => $c ? mb_strtoupper(mb_substr($c->nom, 0, 1).mb_substr($c->prenoms ?? '', 0, 1)) : '?';
    $droits = function (Passage $p) use ($finDroits) {
        if ($p->methode === Passage::METHODE_CAISSE) {
            return 'Entrée journalière';
        }
        if (! $p->client) {
            return '—';
        }
        $fin = $finDroits[$p->client_id] ?? null;
        if ($fin) {
            return 'Jusqu’au '.$fin->format('d/m/Y');
        }
        return $p->client->type === Client::TYPE_ABONNE ? 'Abonnement expiré' : 'Journalier';
    };
@endphp

@section('content')
<div class="top">
    <div>
        <div class="eyebrow">Qui est venu, à quelle heure, et avec quel droit d'accès</div>
        <h1>{{ auth()->user()->isAdmin() ? 'Passages' : 'Passages du jour' }}</h1>
    </div>
    <div class="daynav">
        <a class="icon-btn" href="{{ $lien(['date' => $jour->copy()->subDay()->toDateString()]) }}" aria-label="Jour précédent">@include('partials.icone', ['nom' => 'gauche'])</a>
        <div class="dlabel">
            <strong>{{ $estAujourdhui ? "Aujourd'hui" : ($jour->isYesterday() ? 'Hier' : ucfirst($jour->translatedFormat('l'))) }}</strong>
            <span>{{ $jour->translatedFormat('l j F Y') }}</span>
        </div>
        <a class="icon-btn" href="{{ $estAujourdhui ? '#' : $lien(['date' => $jour->copy()->addDay()->isToday() ? null : $jour->copy()->addDay()->toDateString()]) }}"
           aria-label="Jour suivant" @if($estAujourdhui) aria-disabled="true" tabindex="-1" @endif>@include('partials.icone', ['nom' => 'droite'])</a>
    </div>
</div>

<div class="mini">
    <div><small>Entrées autorisées</small><div class="num">{{ $stats['entrees'] }}</div></div>
    <div><small>Clients distincts</small><div class="num">{{ $stats['distincts'] }}</div></div>
    <div><small>Entrées journalières</small><div class="num">{{ $stats['journaliers'] }}</div></div>
    <div><small>Accès refusés</small><div class="num" style="color:var(--danger)">{{ $stats['refus'] }}</div></div>
</div>

<form method="GET" class="filters">
    @unless($estAujourdhui)<input type="hidden" name="date" value="{{ $jour->toDateString() }}">@endunless
    <input type="hidden" name="filtre" value="{{ $filtre }}">
    <input type="hidden" name="vue" value="{{ $vue }}">
    <label class="fld" for="q">Rechercher<input id="q" type="search" name="q" value="{{ $q }}" placeholder="Nom, téléphone ou n° d'empreinte"></label>
    <button class="btn ghost">Rechercher</button>
    <div class="seg" role="group" aria-label="Filtrer les passages">
        @foreach(PassageController::FILTRES as $cle => $libelle)
            <a href="{{ $lien(['filtre' => $cle]) }}" aria-current="{{ $filtre === $cle ? 'true' : 'false' }}">{{ $libelle }}</a>
        @endforeach
    </div>
    <div class="seg" role="group" aria-label="Affichage">
        <a href="{{ $lien(['vue' => 'passage']) }}" aria-current="{{ $vue === 'passage' ? 'true' : 'false' }}">Par passage</a>
        <a href="{{ $lien(['vue' => 'client']) }}" aria-current="{{ $vue === 'client' ? 'true' : 'false' }}">Par client</a>
    </div>
</form>

<div class="table-wrap">
    <table>
        @if($vue === 'passage')
            <thead><tr><th>Heure</th><th>Client</th><th>Droits</th><th>Pointage</th><th>Résultat</th><th>Venues · 7 j</th><th><span class="sr">Actions</span></th></tr></thead>
            <tbody>
            @forelse($passages as $p)
                <tr class="{{ $p->estAutorise() ? '' : 'refused' }}">
                    <td class="time">{{ $p->passe_le->format('H:i') }}</td>
                    <td>
                        <div class="who">
                            <div class="av {{ $p->estAutorise() ? '' : 'ko' }}">@if($p->client?->photo_url)<img src="{{ $p->client->photo_url }}" alt="">@else{{ $initiales($p->client) }}@endif</div>
                            @if($p->client)
                                <a class="name" href="{{ route('clients.show', $p->client) }}">{{ $p->client->nom_complet }}</a>
                            @else
                                <span class="name">{{ $p->methode === Passage::METHODE_CAISSE ? 'Journalier sans fiche' : $p->identification() }}</span>
                            @endif
                        </div>
                    </td>
                    <td>{{ $droits($p) }}</td>
                    <td>
                        @if($p->methode === Passage::METHODE_CAISSE)
                            <span class="method">@include('partials.icone', ['nom' => 'caisse', 'taille' => 15])Caisse · {{ $p->user?->name ?? '—' }}</span>
                        @else
                            <span class="method">@include('partials.icone', ['nom' => $p->methode === Passage::METHODE_CARTE ? 'carte' : 'empreinte', 'taille' => 15]){{ $p->identification() }}</span>
                        @endif
                    </td>
                    <td>
                        @if($p->estAutorise())
                            <span class="tag ok">Autorisé</span>
                        @else
                            <span class="tag ko">Refusé</span>
                            <div class="meta ko" style="margin-top:4px">{{ $p->message() }}</div>
                        @endif
                    </td>
                    <td>
                        @if($p->client_id && isset($venues7j[$p->client_id]))
                            <strong class="num" style="font-size:18px">{{ $venues7j[$p->client_id] }}</strong> <span class="muted">/ 7</span>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if(! $p->estAutorise() && $estAujourdhui && auth()->user()->isCaissier())
                            @if(in_array($p->motif, ['empreinte_inconnue', 'carte_inconnue'], true))
                                <a class="pill-btn" href="{{ route('clients.create', [$p->motif === 'carte_inconnue' ? 'carte_id' : 'empreinte_id' => $p->empreinte_id]) }}">Enrôler</a>
                            @else
                                <a class="pill-btn" href="{{ route('caisse.index') }}{{ $p->motif === 'abonnement_expire' ? '?client_id='.$p->client_id.'#abonnement' : '' }}">Régulariser</a>
                            @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">Aucun passage{{ $q !== '' || $filtre !== 'tous' ? ' ne correspond à cette recherche' : ' ce jour-là' }}.</td></tr>
            @endforelse
            </tbody>
        @else
            <thead><tr><th>Client</th><th>Droits</th><th>Arrivée</th><th>Entrées ce jour</th><th>Venues · 7 j</th><th>Statut</th></tr></thead>
            <tbody>
            @forelse($parClient as $ligne)
                @php($p = $ligne['passage'])
                <tr class="{{ $ligne['entrees'] === 0 ? 'refused' : '' }}">
                    <td>
                        <div class="who">
                            <div class="av {{ $ligne['entrees'] === 0 ? 'ko' : '' }}">{{ $initiales($p->client) }}</div>
                            @if($p->client)
                                <a class="name" href="{{ route('clients.show', $p->client) }}">{{ $p->client->nom_complet }}</a>
                            @else
                                <span class="name">{{ $p->methode === Passage::METHODE_CAISSE ? 'Journalier sans fiche' : $p->identification() }}</span>
                            @endif
                        </div>
                    </td>
                    <td>{{ $droits($p) }}</td>
                    <td class="time">{{ $ligne['arrivee']->format('H:i') }}</td>
                    <td><strong class="num" style="font-size:18px">{{ $ligne['entrees'] }}</strong>@if($ligne['refus']) <span class="meta ko">· {{ $ligne['refus'] }} refus</span>@endif</td>
                    <td>
                        @if($p->client_id && isset($venues7j[$p->client_id]))
                            <strong class="num" style="font-size:18px">{{ $venues7j[$p->client_id] }}</strong> <span class="muted">/ 7</span>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($ligne['entrees'] === 0)
                            <span class="tag ko">{{ $p->message() }}</span>
                        @elseif($p->methode === Passage::METHODE_CAISSE)
                            <span class="tag info">Payé à la caisse</span>
                        @else
                            <span class="tag ok">En règle</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Aucun client{{ $q !== '' || $filtre !== 'tous' ? ' ne correspond à cette recherche' : ' ce jour-là' }}.</td></tr>
            @endforelse
            </tbody>
        @endif
    </table>
</div>
<p class="muted" style="margin:0;font-size:12px">Un même client qui scanne plusieurs fois en moins de {{ intdiv((int) config('salle.anti_doublon_secondes'), 60) ?: 1 }} minute(s) ne compte qu'une entrée.</p>
@endsection
