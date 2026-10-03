@extends('layouts.app')
@section('title', 'Tableau de bord')

@php
    use App\Models\Paiement;
    use App\Support\Fcfa;
    use Illuminate\Support\Carbon;
    $depuis = fn ($date) => $date ? Carbon::parse($date)->diffForHumans() : 'Jamais venu';
    $maxAffluence = max(1, max($jour['affluence']));
    $recetteJour = $jour['recette_journaliers'] + $jour['recette_abonnements'];
@endphp

@section('content')
<x-page-header title="Tableau de bord" subtitle="Vue d'ensemble de la salle et de la caisse.">
    <form method="GET" class="flex flex-wrap items-end gap-2">
        <div><label class="label text-xs" for="du">Du</label><input id="du" type="date" name="du" value="{{ $du->toDateString() }}" class="input py-2"></div>
        <div><label class="label text-xs" for="au">Au</label><input id="au" type="date" name="au" value="{{ $au->toDateString() }}" class="input py-2"></div>
        <button type="submit" class="btn-dark">Appliquer</button>
    </form>
</x-page-header>

{{-- KPI --}}
<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat label="Recette du jour" :value="Fcfa::format($recetteJour)" icon="cash" tone="brand"
            :hint="'Passages '.Fcfa::format($jour['recette_journaliers']).' · Abonnements '.Fcfa::format($jour['recette_abonnements'])"/>
    <x-stat label="Entrées aujourd'hui" :value="$jour['entrees']" icon="users" tone="green"
            :hint="$jour['refus'].' accès refusé(s)'"/>
    <x-stat label="Abonnements en cours" :value="$abonnementsEnCours" icon="card" tone="blue"
            :hint="$actifs->count().' actifs · '.$moinsActifs->count().' à relancer'"/>
    <x-stat label="Taux de renouvellement" :value="$renouvellement['taux'] !== null ? $renouvellement['taux'].' %' : '—'" icon="trending" tone="amber"
            :hint="$renouvellement['renouveles'].' / '.$renouvellement['echus'].' échus · '.$renouvellement['en_attente']->count().' en délai de grâce'"/>
</div>

<div class="mb-6 grid gap-6 lg:grid-cols-3">
    <section class="card lg:col-span-2">
        <div class="card-header">
            <h2 class="card-title">Affluence par heure</h2>
            <span class="pill-gray">Aujourd'hui</span>
        </div>
        <div class="card-body">
            <div class="flex h-52 items-end gap-1.5">
                @foreach($jour['affluence'] as $heure => $nombre)
                    @continue($heure < 5)
                    <div class="group flex h-full flex-1 flex-col items-center justify-end" title="{{ $heure }}h : {{ $nombre }} entrée(s)">
                        <span class="mb-1 text-[10px] font-semibold text-slate-500 opacity-0 group-hover:opacity-100">{{ $nombre }}</span>
                        <div class="w-full rounded-t-md {{ $nombre > 0 ? 'bg-gradient-to-t from-brand-600 to-brand-400' : 'bg-slate-100' }}"
                             style="height: {{ max(2, round(100 * $nombre / $maxAffluence)) }}%"></div>
                        <span class="mt-1.5 text-[10px] text-slate-400">{{ $heure }}h</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-header"><h2 class="card-title">Caisse du jour</h2><a href="{{ route('admin.paiements.index') }}" class="link text-xs">Détail →</a></div>
        <div class="card-body space-y-4 text-sm">
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Par caissière</p>
                <ul class="space-y-2">
                    @forelse($jour['recette_par_caissier'] as $ligne)
                        <li class="flex justify-between"><span>{{ $ligne['nom'] }} <span class="text-slate-400">({{ $ligne['nombre'] }})</span></span><span class="font-semibold">{{ Fcfa::format($ligne['total']) }}</span></li>
                    @empty
                        <li class="text-slate-400">Aucun encaissement</li>
                    @endforelse
                </ul>
            </div>
            @if($jour['recette_par_mode']->isNotEmpty())
                <div class="border-t border-slate-100 pt-4">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Par mode de paiement</p>
                    <ul class="space-y-2">
                        @foreach($jour['recette_par_mode'] as $mode => $total)
                            <li class="flex justify-between"><span>{{ Paiement::MODES[$mode] ?? $mode }}</span><span class="font-semibold">{{ Fcfa::format($total) }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </section>
</div>

<div class="grid gap-6 lg:grid-cols-2">
    <section class="card">
        <div class="card-header">
            <div><h2 class="card-title">À relancer</h2><p class="text-xs text-slate-500">Abonnement en cours, aucune venue depuis {{ config('salle.kpi.inactif_jours') }} jours</p></div>
            <span class="pill-amber">{{ $moinsActifs->count() }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Client</th><th>Téléphone</th><th>Dernière venue</th></tr></thead>
                <tbody>
                @forelse($moinsActifs->take(15) as $client)
                    <tr>
                        <td><a href="{{ route('clients.show', $client) }}" class="link">{{ $client->nom_complet }}</a></td>
                        <td>{{ $client->telephone ?? '—' }}</td>
                        <td class="text-slate-500">{{ $depuis($client->dernier_passage_le) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-8 text-center text-slate-400">Tous les abonnés viennent régulièrement 🎉</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card-header">
            <div><h2 class="card-title">Expirent bientôt</h2><p class="text-xs text-slate-500">Dans les {{ config('salle.kpi.expiration_alerte_jours') }} jours, pas encore renouvelés</p></div>
            <span class="pill-red">{{ $expirantBientot->count() }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Client</th><th>Formule</th><th>Fin</th></tr></thead>
                <tbody>
                @forelse($expirantBientot as $abonnement)
                    <tr>
                        <td><a href="{{ route('clients.show', $abonnement->client) }}" class="link">{{ $abonnement->client->nom_complet }}</a>
                            <span class="block text-xs text-slate-500">{{ $abonnement->client->telephone }}</span></td>
                        <td>{{ $abonnement->formule->nom }}</td>
                        <td><span class="{{ $abonnement->joursRestants() <= 2 ? 'pill-red' : 'pill-amber' }}">{{ $abonnement->date_fin->format('d/m') }} · {{ $abonnement->joursRestants() }} j</span></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-8 text-center text-slate-400">Aucune échéance proche</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card-header"><h2 class="card-title">Abonnés les plus assidus</h2><span class="pill-green">{{ $actifs->count() }} actifs</span></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Client</th><th>Venues (30 j)</th><th>Dernière venue</th></tr></thead>
                <tbody>
                @forelse($actifs->take(10) as $client)
                    <tr>
                        <td><a href="{{ route('clients.show', $client) }}" class="link">{{ $client->nom_complet }}</a></td>
                        <td><span class="font-semibold">{{ $client->passages_30j }}</span></td>
                        <td class="text-slate-500">{{ $depuis($client->dernier_passage_le) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-8 text-center text-slate-400">Aucun abonné actif sur les {{ config('salle.kpi.actif_jours') }} derniers jours</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card-header"><h2 class="card-title">Renouvellements ({{ $du->format('d/m') }} – {{ $au->format('d/m') }})</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Client</th><th>Formule</th><th>Le</th></tr></thead>
                <tbody>
                @forelse($renouvellements->take(10) as $abonnement)
                    <tr>
                        <td><a href="{{ route('clients.show', $abonnement->client) }}" class="link">{{ $abonnement->client->nom_complet }}</a></td>
                        <td>{{ $abonnement->formule->nom }}</td>
                        <td class="text-slate-500">{{ $abonnement->created_at->format('d/m/Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-8 text-center text-slate-400">Aucun renouvellement sur la période</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($renouvellement['perdus']->isNotEmpty())
            <div class="border-t border-slate-100 p-5">
                <p class="mb-2 text-sm font-semibold text-red-700">Non renouvelés ({{ $renouvellement['perdus']->count() }})</p>
                <ul class="space-y-1 text-sm text-slate-600">
                    @foreach($renouvellement['perdus']->take(10) as $abonnement)
                        <li>{{ $abonnement->client->nom_complet }} — fin le {{ $abonnement->date_fin->format('d/m/Y') }} · {{ $abonnement->client->telephone ?? 'pas de téléphone' }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>
</div>
@endsection
