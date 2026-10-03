@extends('layouts.app')
@section('title', 'Tableau de bord')

@php
    use App\Models\Paiement;
    use App\Services\KpiService;
    use App\Support\Fcfa;
@endphp

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-3xl font-bold tracking-tight text-slate-900">Tableau de bord</h1>
        <p class="mt-1 text-slate-500">Suivez vos encaissements et l'activité de la journée</p>
    </div>
    <span class="card inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-slate-700">
        <x-icon name="calendar" class="size-5 text-slate-500"/> {{ today()->format('d/m/Y') }}
    </span>
</div>

<div class="mb-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
    <x-kpi label="Revenus encaissés aujourd'hui" :value="Fcfa::format($aujourdhui['recette'])" icon="cash" tone="green"
           :variation="KpiService::variation($aujourdhui['recette'], $hier['recette'])"/>
    <x-kpi label="Visites / passages du jour" :value="$aujourdhui['entrees']" icon="user" tone="blue"
           :variation="KpiService::variation($aujourdhui['entrees'], $hier['entrees'])"/>
    <x-kpi label="Abonnements vendus" :value="$aujourdhui['abonnements']" icon="calendar" tone="purple"
           :variation="KpiService::variation($aujourdhui['abonnements'], $hier['abonnements'])"/>
    <x-kpi label="Renouvellements du jour" :value="$aujourdhui['renouvellements']" icon="refresh" tone="orange"
           :variation="KpiService::variation($aujourdhui['renouvellements'], $hier['renouvellements'])"/>
</div>

<div class="mb-6 grid gap-6 xl:grid-cols-5">
    {{-- Actions rapides --}}
    <section class="card grid overflow-hidden sm:grid-cols-[minmax(0,15rem)_1fr] xl:col-span-3">
        <div class="relative hidden min-h-56 sm:block">
            <img src="{{ asset('images/encaissements.webp') }}" alt="" class="absolute inset-0 size-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-br from-ink-900/90 via-ink-900/70 to-ink-800/40"></div>
            <div class="relative p-6 text-white">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-300">Encaissements</p>
                <p class="mt-3 text-3xl font-bold leading-tight">Chaque effort compte !</p>
                <p class="mt-3 text-sm text-slate-200">Merci pour votre engagement au quotidien.</p>
            </div>
        </div>
        <div class="p-6">
            <h2 class="mb-4 text-lg font-semibold text-slate-900">Actions rapides</h2>
            <div class="space-y-3">
                <a href="{{ route('caisse.index', ['onglet' => 'passage']) }}" class="flex items-center gap-4 rounded-xl bg-brand-500 px-5 py-3.5 text-white shadow-sm transition hover:bg-brand-600">
                    <span class="inline-flex size-9 items-center justify-center rounded-full bg-white/20"><x-icon name="ticket" class="size-5"/></span>
                    <span><span class="block font-semibold">Nouveau passage</span><span class="block text-xs text-white/80">Client de passage (ticket)</span></span>
                </a>
                <a href="{{ route('caisse.index', ['onglet' => 'abonnement']) }}" class="flex items-center gap-4 rounded-xl bg-blue-600 px-5 py-3.5 text-white shadow-sm transition hover:bg-blue-700">
                    <span class="inline-flex size-9 items-center justify-center rounded-full bg-white/20"><x-icon name="plus" class="size-5"/></span>
                    <span><span class="block font-semibold">Nouvel abonnement</span><span class="block text-xs text-white/80">Créer un abonnement</span></span>
                </a>
                <a href="{{ route('caisse.index', ['onglet' => 'renouvellement']) }}" class="flex items-center gap-4 rounded-xl bg-violet-600 px-5 py-3.5 text-white shadow-sm transition hover:bg-violet-700">
                    <span class="inline-flex size-9 items-center justify-center rounded-full bg-white/20"><x-icon name="refresh" class="size-5"/></span>
                    <span><span class="block font-semibold">Renouvellement</span><span class="block text-xs text-white/80">Renouveler un abonnement</span></span>
                </a>
            </div>
        </div>
    </section>

    <section class="card p-6 xl:col-span-2">
        <h2 class="text-lg font-semibold text-slate-900">Évolution de mes encaissements <span class="text-sm font-normal text-slate-500">(7 derniers jours)</span></h2>
        <x-chart.barres class="mt-4" titre="Mes encaissements des 7 derniers jours"
            :etiquettes="array_map(fn ($r) => $r['jour']->format('d/m'), $recettes)"
            :valeurs="array_map(fn ($r) => $r['abonnement'] + $r['journalier'], $recettes)"/>
    </section>
</div>

<section class="card overflow-hidden">
    <div class="card-header">
        <h2 class="text-lg font-semibold text-slate-900">Transactions récentes</h2>
        <a href="{{ route('caisse.index') }}" class="link inline-flex items-center gap-1 text-sm">Aller à la caisse <x-icon name="arrow-right" class="size-4"/></a>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead><tr><th>Heure</th><th>Client</th><th>Type</th><th>Mode de paiement</th><th>Montant</th><th>Statut</th><th></th></tr></thead>
            <tbody>
            @forelse($transactions as $p)
                <tr>
                    <td class="text-slate-600">{{ $p->created_at->format('H:i') }}</td>
                    <td><span class="inline-flex items-center gap-2"><x-icon name="user" class="size-4 text-slate-400"/>{{ $p->client?->nom_complet ?? 'Client anonyme' }}</span></td>
                    <td>
                        @if(! $p->abonnement)<span class="pill-blue">Passage{{ $p->quantite > 1 ? ' × '.$p->quantite : '' }}</span>
                        @elseif($p->abonnement->est_renouvellement)<span class="pill bg-violet-50 text-violet-700 ring-1 ring-violet-600/20">Renouvellement</span>
                        @else<span class="pill-green">Abonnement</span>@endif
                    </td>
                    <td>{{ Paiement::MODES[$p->mode] ?? $p->mode }}</td>
                    <td class="font-semibold {{ $p->estAnnule() ? 'text-slate-400 line-through' : '' }}">{{ Fcfa::format($p->montant) }}</td>
                    <td>@if($p->estAnnule())<span class="pill-red"><x-icon name="x" class="size-3"/> Annulé</span>@else<span class="pill-green"><x-icon name="check" class="size-3"/> Validé</span>@endif</td>
                    <td class="text-right"><a href="{{ route('recus.show', $p) }}" class="link text-xs">Ticket</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-10 text-center text-slate-400">Aucun encaissement aujourd'hui. Utilisez les actions rapides ci-dessus.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
