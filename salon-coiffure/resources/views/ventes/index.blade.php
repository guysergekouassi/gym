@php use App\Support\Fcfa; @endphp
@extends('layouts.app')

@section('titre', 'Historique des ventes')

@section('contenu')
<div class="page-titre">
    <div>
        <h2>@include('partials.icone', ['nom' => 'liste']) Historique des ventes</h2>
        <p>{{ $periode->libelle() }} · {{ $ventes->total() }} ticket(s)</p>
    </div>
    @include('partials.selecteur-periode', ['periode' => $periode, 'route' => 'ventes.index', 'extra' => array_filter(request()->only('user_id', 'statut'))])
</div>

@error('motif')<div class="alerte alerte-erreur">{{ $message }}</div>@enderror

<form method="GET" class="filtres-dates" style="margin-bottom:16px">
    @foreach(request()->only('periode', 'du', 'au') as $cle => $valeur)<input type="hidden" name="{{ $cle }}" value="{{ $valeur }}">@endforeach
    <select name="user_id" style="width:auto">
        <option value="">Toutes les personnes</option>
        @foreach($personnes as $p)<option value="{{ $p->id }}" @selected(request('user_id') == $p->id)>{{ $p->name }}</option>@endforeach
    </select>
    <select name="statut" style="width:auto">
        <option value="">Tous les tickets</option>
        <option value="annulees" @selected(request('statut') === 'annulees')>Annulés uniquement</option>
    </select>
    <button class="btn btn-petit" type="submit">Filtrer</button>
</form>

<div class="carte">
    @include('ventes.tableau', ['ventes' => $ventes])
    @if($ventes->hasPages())
        <div class="pagination">
            @if(! $ventes->onFirstPage())<a href="{{ $ventes->previousPageUrl() }}">← Précédent</a>@endif
            <span class="courant">Page {{ $ventes->currentPage() }} / {{ $ventes->lastPage() }}</span>
            @if($ventes->hasMorePages())<a href="{{ $ventes->nextPageUrl() }}">Suivant →</a>@endif
        </div>
    @endif
</div>
@endsection
