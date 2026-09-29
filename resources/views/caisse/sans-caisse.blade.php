@extends('layouts.app')
@section('title', 'Caisse indisponible')

@section('content')
<div class="top"><div><h1>Caisse indisponible</h1></div></div>
<section class="card form-card">
    <h2>Aucune caisse active ne vous est attribuée</h2>
    <p class="muted" style="margin:0">Vous ne pouvez pas encaisser tant que l'administrateur ne vous a pas rattachée à une caisse, ou si votre caisse a été désactivée. Vous pouvez tout de même consulter les clients et les passages du jour.</p>
    <div class="actions">
        <a href="{{ route('clients.index') }}" class="btn ghost">Voir les clients</a>
        <a href="{{ route('passages.index') }}" class="btn ghost">Passages du jour</a>
    </div>
</section>
@endsection
