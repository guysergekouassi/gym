@extends('layouts.app')
@section('title', $client->nom_complet)

@php
    use App\Models\Abonnement;
    use App\Models\Client;
    use App\Models\Paiement;
    use App\Models\Passage;
    use App\Support\Fcfa;
@endphp

@section('content')
<div class="card mb-6 overflow-hidden">
    <div class="h-24 bg-gradient-to-r from-ink-950 via-ink-900 to-brand-700"></div>
    <div class="flex flex-wrap items-end gap-5 px-6 pb-6">
        <x-avatar :client="$client" size="size-24" text="text-3xl" class="-mt-12 ring-4"/>
        <div class="min-w-0 flex-1 pt-3">
            <h1 class="text-2xl font-bold text-slate-900">{{ $client->nom_complet }}</h1>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                <span class="{{ $client->type === Client::TYPE_ABONNE ? 'pill-blue' : 'pill-gray' }}">{{ Client::TYPES[$client->type] ?? $client->type }}</span>
                @if($finDroits)
                    <span class="pill-green">Droits jusqu'au {{ $finDroits->format('d/m/Y') }}</span>
                @elseif($client->type === Client::TYPE_ABONNE)
                    <span class="pill-red">Abonnement expiré</span>
                @endif
                <span class="pill-gray"><x-icon name="fingerprint" class="size-3.5"/> {{ $client->empreinte_id ? 'Empreinte n°'.$client->empreinte_id : 'Empreinte non enregistrée' }}</span>
                @if($client->telephone)<span class="text-slate-500">{{ $client->telephone }}</span>@endif
                @if($client->date_adhesion)<span class="text-slate-500">· Membre depuis le {{ $client->date_adhesion->format('d/m/Y') }}</span>@endif
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('caisse.index', ['client_id' => $client->id]) }}" class="btn-primary"><x-icon name="card" class="size-4"/> Abonner / renouveler</a>
            <a href="{{ route('clients.edit', $client) }}" class="btn-light"><x-icon name="pencil" class="size-4"/> Modifier</a>
            @if(auth()->user()->isAdmin())
                <form method="POST" action="{{ route('clients.destroy', $client) }}" data-confirm="Archiver {{ $client->nom_complet }} ? Son badge sera libéré.">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-danger"><x-icon name="archive" class="size-4"/> Archiver</button>
                </form>
            @endif
        </div>
    </div>
</div>

<div class="mb-6 grid gap-4 sm:grid-cols-3">
    <x-stat label="Venues (30 derniers jours)" :value="$venues30j" icon="trending" tone="green"/>
    <x-stat label="Jours restants" :value="$finDroits ? max(0, (int) today()->diffInDays($finDroits, false)) : '—'" icon="clock" tone="blue"/>
    <x-stat label="Total payé" :value="Fcfa::format($client->paiements->reject->estAnnule()->sum('montant'))" icon="cash" tone="brand" hint="Sur les 20 derniers paiements"/>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <section class="card">
        <div class="card-header"><h2 class="card-title">Abonnements</h2></div>
        <ul class="divide-y divide-slate-100">
            @forelse($client->abonnements as $abonnement)
                @php
                    $annule = $abonnement->statut === Abonnement::STATUT_ANNULE;
                    $enCours = ! $annule && $abonnement->date_debut->lte(today()) && $abonnement->date_fin->gte(today());
                    $aVenir = ! $annule && $abonnement->date_debut->gt(today());
                @endphp
                <li class="px-5 py-3 text-sm {{ $annule ? 'opacity-50' : '' }}">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-semibold {{ $annule ? 'line-through' : '' }}">{{ $abonnement->formule->nom }}</span>
                        @if($annule)<span class="pill-red">Annulé</span>
                        @elseif($enCours)<span class="pill-green">En cours</span>
                        @elseif($aVenir)<span class="pill-blue">À venir</span>
                        @else<span class="pill-gray">Terminé</span>@endif
                    </div>
                    <p class="mt-0.5 text-slate-500">{{ $abonnement->date_debut->format('d/m/Y') }} → {{ $abonnement->date_fin->format('d/m/Y') }} · {{ Fcfa::format($abonnement->montant) }}</p>
                </li>
            @empty
                <li class="px-5 py-8 text-center text-sm text-slate-400">Aucun abonnement</li>
            @endforelse
        </ul>
    </section>

    <section class="card">
        <div class="card-header"><h2 class="card-title">Derniers passages</h2></div>
        <ul class="max-h-[28rem] divide-y divide-slate-100 overflow-y-auto">
            @forelse($passages as $passage)
                <li class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm">
                    <span>
                        <span class="block font-medium">{{ $passage->passe_le->format('d/m/Y H:i') }}</span>
                        <span class="text-xs text-slate-500">{{ $passage->methode === Passage::METHODE_EMPREINTE ? 'Empreinte' : 'Caisse' }}</span>
                    </span>
                    @if($passage->estAutorise())<span class="pill-green">Entré</span>@else<span class="pill-red">{{ $passage->message() }}</span>@endif
                </li>
            @empty
                <li class="px-5 py-8 text-center text-sm text-slate-400">Aucun passage</li>
            @endforelse
        </ul>
    </section>

    <section class="card">
        <div class="card-header"><h2 class="card-title">Paiements</h2></div>
        <ul class="divide-y divide-slate-100">
            @forelse($client->paiements as $paiement)
                <li class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm {{ $paiement->estAnnule() ? 'opacity-50' : '' }}">
                    <span>
                        <a href="{{ route('recus.show', $paiement) }}" class="link">{{ $paiement->numero_recu }}</a>
                        <span class="block text-xs text-slate-500">{{ $paiement->created_at->format('d/m/Y') }} · {{ Paiement::TYPES[$paiement->type] ?? $paiement->type }}{{ $paiement->estAnnule() ? ' · annulé' : '' }}</span>
                    </span>
                    <span class="font-semibold {{ $paiement->estAnnule() ? 'line-through' : '' }}">{{ Fcfa::format($paiement->montant) }}</span>
                </li>
            @empty
                <li class="px-5 py-8 text-center text-sm text-slate-400">Aucun paiement</li>
            @endforelse
        </ul>
    </section>
</div>

@if($client->notes)
    <div class="card card-body mt-6 text-sm"><p class="mb-1 font-semibold">Notes</p><p class="whitespace-pre-line text-slate-600">{{ $client->notes }}</p></div>
@endif
@endsection
