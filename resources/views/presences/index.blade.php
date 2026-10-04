@extends('layouts.app')
@section('title', 'Entrées / départs')

@php
    use App\Http\Controllers\PresenceController;
    use App\Models\Client;
    use App\Models\Passage;
@endphp

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-3xl font-bold tracking-tight text-slate-900">Entrées / départs</h1>
        <p class="mt-1 text-slate-500">1<sup>er</sup> badge du jour = arrivée, 2<sup>e</sup> badge = départ. Heures à la seconde près.</p>
    </div>
    <x-filtre-periode :periode="$periode" :annees="$annees" :conserver="['q' => request('q')]"/>
</div>

<div class="mb-6 grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 2xl:gap-5">
    <x-kpi :label="'Entrées '.$periode->suffixe()" :value="$chiffres['entrees']" icon="arrow-right" tone="green" hint="arrivées (badge ou ticket)"/>
    <x-kpi :label="'Départs '.$periode->suffixe()" :value="$chiffres['departs']" icon="logout" tone="blue" hint="2e badge du jour"/>
    <x-kpi label="Présents en ce moment" :value="$chiffres['presents']" icon="users" tone="orange" :hint="$salleFermee ? 'salle fermée' : 'arrivés aujourd\'hui, pas encore repartis'"/>
    <x-kpi label="Durée moyenne" :value="PresenceController::duree($chiffres['duree_moyenne'])" icon="clock" tone="purple" hint="entre l'arrivée et le départ"/>
    <x-kpi :label="'Accès refusés '.$periode->suffixe()" :value="$chiffres['refus']" icon="x" tone="red" hint="abonnement expiré, doigt inconnu…"/>
</div>

<div class="card mb-6 flex flex-wrap items-center gap-3 p-3">
    <p class="px-2 text-sm text-slate-600">{{ $lignes->total() }} arrivée(s) · <span class="first-letter:uppercase">{{ $periode->libelle() }}</span></p>
    <form method="GET" class="ml-auto flex flex-wrap items-center gap-2">
        @foreach($periode->parametres() as $nom => $valeur)<input type="hidden" name="{{ $nom }}" value="{{ $valeur }}">@endforeach
        <div class="relative">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"/>
            <input type="search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Nom, téléphone ou n° empreinte" class="input w-64 py-2 pl-9">
        </div>
        <button type="submit" class="btn-dark py-2">Rechercher</button>
    </form>
</div>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
            <tr><th>Client</th><th>Date</th><th>Arrivée</th><th>Départ</th><th>Durée</th><th>Moyen</th></tr>
            </thead>
            <tbody>
            @forelse($lignes as $ligne)
                <tr>
                    <td>
                        @if($ligne->client)
                            <a href="{{ route('clients.show', $ligne->client) }}" class="flex items-center gap-3">
                                <x-avatar :client="$ligne->client" size="size-9" text="text-xs"/>
                                <span>
                                    <span class="block font-semibold text-slate-900">{{ $ligne->client->nom_complet }}</span>
                                    <span class="text-xs text-slate-500">{{ $ligne->client->type === Client::TYPE_ABONNE ? 'Abonné' : 'Passage' }}@if($ligne->client->empreinte_id) · n° {{ $ligne->client->empreinte_id }}@endif</span>
                                </span>
                            </a>
                        @else
                            <span class="flex items-center gap-3">
                                <span class="inline-flex size-9 items-center justify-center rounded-full bg-orange-50 text-orange-500"><x-icon name="ticket" class="size-4"/></span>
                                <span class="font-semibold text-slate-900">Client de passage</span>
                            </span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-slate-600">{{ $ligne->passe_le->translatedFormat('D d/m/Y') }}</td>
                    <td class="whitespace-nowrap font-mono font-semibold text-emerald-700">{{ $ligne->passe_le->format('H:i:s') }}</td>
                    <td class="whitespace-nowrap">
                        @if($ligne->depart_le)
                            <span class="font-mono font-semibold text-sky-700">{{ $ligne->depart_le->format('H:i:s') }}</span>
                        @elseif($ligne->methode === Passage::METHODE_EMPREINTE && $ligne->passe_le->isToday() && ! $salleFermee)
                            <span class="pill-green">Encore présent</span>
                        @elseif($ligne->methode === Passage::METHODE_EMPREINTE)
                            <span class="pill-gray" title="Le membre n'a pas badgé en sortant">Non badgé</span>
                        @else
                            <span class="pill-gray" title="Entrée par ticket de caisse : pas de badge de sortie">Non défini</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-slate-600">{{ $ligne->depart_le ? PresenceController::duree((int) $ligne->passe_le->diffInSeconds($ligne->depart_le)) : '—' }}</td>
                    <td class="whitespace-nowrap text-slate-600">
                        @if($ligne->methode === Passage::METHODE_EMPREINTE)
                            <span class="inline-flex items-center gap-1.5"><x-icon name="fingerprint" class="size-4 text-slate-400"/> {{ $ligne->lecteur?->nom ?? 'Empreinte' }}</span>
                        @else
                            <span class="inline-flex items-center gap-1.5"><x-icon name="ticket" class="size-4 text-slate-400"/> Ticket caisse{{ $ligne->user ? ' · '.$ligne->user->name : '' }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-12 text-center text-slate-400">Aucune entrée sur cette période.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4">{{ $lignes->links() }}</div>
@endsection
