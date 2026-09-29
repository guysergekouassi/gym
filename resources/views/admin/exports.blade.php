@extends('layouts.app')
@section('title', 'Exports Excel')

@section('content')
<div class="top"><div><div class="eyebrow">Fichiers CSV qui s'ouvrent directement dans Excel, pour le comptable ou vos propres tableaux</div><h1>Exports Excel</h1></div></div>

<div class="grid g3" style="align-items:start">
    <form method="GET" action="{{ route('admin.exports.telecharger', 'paiements') }}" class="card">
        <h2>Encaissements</h2>
        <p class="sub" style="margin:0">Un reçu par ligne : date, caisse, caissière, client, objet, mode, montant. Les reçus annulés sont inclus et marqués.</p>
        <div class="split">
            <label class="fld" for="p-du">Du<input id="p-du" type="date" name="du" value="{{ today()->startOfMonth()->toDateString() }}"></label>
            <label class="fld" for="p-au">Au<input id="p-au" type="date" name="au" value="{{ today()->toDateString() }}"></label>
        </div>
        <label class="fld" for="p-caisse">Caisse<select id="p-caisse" name="caisse_id"><option value="">Toutes les caisses</option>@foreach($caisses as $c)<option value="{{ $c->id }}">{{ $c->nom }}</option>@endforeach</select></label>
        <button class="btn">@include('partials.icone', ['nom' => 'export'])Télécharger</button>
    </form>

    <form method="GET" action="{{ route('admin.exports.telecharger', 'passages') }}" class="card">
        <h2>Passages</h2>
        <p class="sub" style="margin:0">Toutes les entrées et tous les refus, avec la salle et le moyen de pointage.</p>
        <div class="split">
            <label class="fld" for="x-du">Du<input id="x-du" type="date" name="du" value="{{ today()->startOfMonth()->toDateString() }}"></label>
            <label class="fld" for="x-au">Au<input id="x-au" type="date" name="au" value="{{ today()->toDateString() }}"></label>
        </div>
        <button class="btn">@include('partials.icone', ['nom' => 'export'])Télécharger</button>
    </form>

    <form method="GET" action="{{ route('admin.exports.telecharger', 'clients') }}" class="card">
        <h2>Clients</h2>
        <p class="sub" style="margin:0">La liste complète avec téléphone, n° d'empreinte et de carte, et fin des droits.</p>
        <button class="btn">@include('partials.icone', ['nom' => 'export'])Télécharger</button>
    </form>
</div>
@endsection
