@extends('layouts.app')
@section('title', $client->nom_complet)

@php
    use App\Models\Client;
    use App\Models\Paiement;
    use App\Models\Passage;
    use App\Support\Fcfa;
@endphp

@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm flex flex-wrap gap-6 items-center mb-6">
    @if($client->photo_url)
        <img src="{{ $client->photo_url }}" alt="" class="h-24 w-24 rounded-full object-cover">
    @else
        <div class="h-24 w-24 rounded-full bg-slate-200 flex items-center justify-center text-3xl font-bold text-slate-500">{{ mb_substr($client->nom, 0, 1) }}</div>
    @endif
    <div class="flex-1">
        <h1 class="text-2xl font-bold">{{ $client->nom_complet }}</h1>
        <p class="text-slate-500">{{ Client::TYPES[$client->type] ?? $client->type }} · {{ $client->telephone ?? 'pas de téléphone' }} · Empreinte {{ $client->empreinte_id ? '#'.$client->empreinte_id : 'non enrôlée' }}</p>
        <p class="mt-2">
            @if($finDroits)
                <span class="inline-block rounded-full bg-emerald-100 text-emerald-800 px-3 py-1 text-sm">Droits jusqu'au {{ $finDroits->format('d/m/Y') }}</span>
            @elseif($client->type === Client::TYPE_ABONNE)
                <span class="inline-block rounded-full bg-red-100 text-red-800 px-3 py-1 text-sm">Abonnement expiré</span>
            @endif
        </p>
    </div>
    <div class="flex flex-col gap-2 text-sm">
        <a href="{{ route('caisse.index', ['client_id' => $client->id]) }}" class="rounded-lg bg-sky-600 text-white px-4 py-2 text-center">Abonner / renouveler</a>
        <a href="{{ route('clients.edit', $client) }}" class="rounded-lg bg-slate-200 px-4 py-2 text-center">Modifier</a>
        @if(auth()->user()->isAdmin())
            <form method="POST" action="{{ route('clients.destroy', $client) }}" onsubmit="return confirm('Archiver ce client ?')">
                @csrf @method('DELETE')
                <button class="w-full rounded-lg bg-red-50 text-red-700 px-4 py-2">Archiver</button>
            </form>
        @endif
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-4">
    <section class="bg-white rounded-xl p-4 shadow-sm">
        <h2 class="font-semibold mb-3">Abonnements</h2>
        <ul class="text-sm space-y-2">
            @forelse($client->abonnements as $abonnement)
                <li class="border-b pb-2">
                    <span class="font-medium">{{ $abonnement->formule->nom }}</span>
                    @if($abonnement->est_renouvellement)<span class="text-xs text-sky-700">(renouvellement)</span>@endif
                    <br>{{ $abonnement->date_debut->format('d/m/Y') }} → {{ $abonnement->date_fin->format('d/m/Y') }} · {{ Fcfa::format($abonnement->montant) }}
                </li>
            @empty
                <li class="text-slate-400">Aucun abonnement</li>
            @endforelse
        </ul>
    </section>

    <section class="bg-white rounded-xl p-4 shadow-sm">
        <h2 class="font-semibold mb-3">Derniers passages</h2>
        <ul class="text-sm space-y-1">
            @forelse($passages as $passage)
                <li class="flex justify-between">
                    <span>{{ $passage->passe_le->format('d/m/Y H:i') }} · {{ $passage->methode === Passage::METHODE_EMPREINTE ? 'Empreinte' : 'Caisse' }}</span>
                    <span class="{{ $passage->estAutorise() ? 'text-emerald-700' : 'text-red-700' }}">{{ $passage->estAutorise() ? 'Entré' : $passage->message() }}</span>
                </li>
            @empty
                <li class="text-slate-400">Aucun passage</li>
            @endforelse
        </ul>
    </section>

    <section class="bg-white rounded-xl p-4 shadow-sm">
        <h2 class="font-semibold mb-3">Paiements</h2>
        <ul class="text-sm space-y-1">
            @forelse($client->paiements as $paiement)
                <li class="flex justify-between">
                    <a href="{{ route('recus.show', $paiement) }}" class="text-sky-700 hover:underline">{{ $paiement->created_at->format('d/m/Y') }} · {{ Paiement::TYPES[$paiement->type] ?? $paiement->type }}</a>
                    <span>{{ Fcfa::format($paiement->montant) }}</span>
                </li>
            @empty
                <li class="text-slate-400">Aucun paiement</li>
            @endforelse
        </ul>
    </section>
</div>
@endsection
