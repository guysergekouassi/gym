@extends('layouts.membre')
@section('title', 'Renouveler')

@php use App\Support\Fcfa; @endphp

@section('content')
<h1 style="font-family:var(--f-display);font-size:34px;margin:0">Renouveler mon abonnement</h1>
<p class="muted" style="margin:0">{{ $finDroits ? 'Le nouvel abonnement démarre le '.$finDroits->copy()->addDay()->format('d/m/Y').' : vous ne perdez aucun jour.' : 'Le nouvel abonnement démarre aujourd’hui.' }}</p>

@if($actif)
    <form method="POST" action="{{ route('membre.renouveler') }}" class="grid" style="gap:10px">
        @csrf
        @foreach($formules as $f)
            <label class="choice">
                <input type="radio" name="formule_id" value="{{ $f->id }}" required @checked($loop->first)>
                <span class="box" style="min-height:0;flex-direction:row;justify-content:space-between;align-items:center">
                    <span><strong>{{ $f->nom }}</strong><br><small>{{ $f->resume() }}</small></span>
                    <span class="num" style="font-size:26px">{{ number_format($f->prix, 0, ',', ' ') }} F</span>
                </span>
            </label>
        @endforeach
        <button class="btn xl">Payer par Mobile Money</button>
        <p class="meta" style="margin:0;text-align:center">Orange Money, MTN MoMo, Moov Money, Wave ou carte, via une page de paiement sécurisée.</p>
    </form>
@else
    <section class="card">
        <h2>Paiement à l'accueil</h2>
        <p style="margin:0">Le paiement en ligne n'est pas encore disponible. Passez à l'accueil : espèces, Orange Money, MTN MoMo, Moov Money et Wave sont acceptés.</p>
        @foreach($formules as $f)
            <div class="line"><span>{{ $f->nom }} <span class="muted">· {{ $f->resume() }}</span></span><span class="num">{{ Fcfa::format($f->prix) }}</span></div>
        @endforeach
    </section>
@endif
@endsection
