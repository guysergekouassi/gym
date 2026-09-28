@extends('layouts.app')
@section('title', 'Tableau de bord')

@php
    use App\Support\Fcfa;
    use Illuminate\Support\Carbon;
    $depuis = fn ($date) => $date ? Carbon::parse($date)->diffForHumans() : 'Jamais venu';
    $maxAffluence = max(1, max($jour['affluence']));
@endphp

@section('content')
<div class="flex flex-wrap items-end justify-between gap-4 mb-6">
    <h1 class="text-2xl font-bold">Tableau de bord</h1>
    <form method="GET" class="flex items-end gap-2 text-sm">
        <label>Du <input type="date" name="du" value="{{ $du->toDateString() }}" class="block border rounded px-2 py-1"></label>
        <label>Au <input type="date" name="au" value="{{ $au->toDateString() }}" class="block border rounded px-2 py-1"></label>
        <button class="rounded bg-slate-800 text-white px-3 py-1.5">Filtrer</button>
    </form>
</div>

{{-- Cartes KPI --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl p-4 shadow-sm">
        <p class="text-sm text-slate-500">Abonnements en cours</p>
        <p class="text-3xl font-bold">{{ $abonnementsEnCours }}</p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm">
        <p class="text-sm text-slate-500">Abonnés actifs ({{ config('salle.kpi.actif_jours') }} j)</p>
        <p class="text-3xl font-bold text-emerald-600">{{ $actifs->count() }}</p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm">
        <p class="text-sm text-slate-500">Moins actifs ({{ config('salle.kpi.inactif_jours') }} j sans venir)</p>
        <p class="text-3xl font-bold text-amber-600">{{ $moinsActifs->count() }}</p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm">
        <p class="text-sm text-slate-500">Taux de renouvellement</p>
        <p class="text-3xl font-bold text-sky-600">{{ $renouvellement['taux'] !== null ? $renouvellement['taux'].' %' : '—' }}</p>
        <p class="text-xs text-slate-500">{{ $renouvellement['renouveles'] }} / {{ $renouvellement['echus'] }} échus · {{ $renouvellement['en_attente']->count() }} en délai de grâce</p>
    </div>
</div>

{{-- Aujourd'hui --}}
<div class="grid lg:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl p-4 shadow-sm">
        <h2 class="font-semibold mb-3">Aujourd'hui</h2>
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt>Entrées</dt><dd class="font-semibold">{{ $jour['entrees'] }}</dd></div>
            <div class="flex justify-between"><dt>Accès refusés</dt><dd class="font-semibold text-red-600">{{ $jour['refus'] }}</dd></div>
            <div class="flex justify-between"><dt>Recette journaliers</dt><dd class="font-semibold">{{ Fcfa::format($jour['recette_journaliers']) }}</dd></div>
            <div class="flex justify-between"><dt>Recette abonnements</dt><dd class="font-semibold">{{ Fcfa::format($jour['recette_abonnements']) }}</dd></div>
        </dl>
        <h3 class="font-semibold mt-4 mb-2 text-sm">Par caissière</h3>
        <ul class="text-sm space-y-1">
            @forelse($jour['recette_par_caissier'] as $ligne)
                <li class="flex justify-between"><span>{{ $ligne['nom'] }} ({{ $ligne['nombre'] }})</span><span>{{ Fcfa::format($ligne['total']) }}</span></li>
            @empty
                <li class="text-slate-400">Aucun encaissement</li>
            @endforelse
        </ul>
    </div>

    <div class="bg-white rounded-xl p-4 shadow-sm lg:col-span-2">
        <h2 class="font-semibold mb-3">Affluence par heure (aujourd'hui)</h2>
        <div class="flex items-end gap-1 h-40">
            @foreach($jour['affluence'] as $heure => $nombre)
                <div class="flex-1 flex flex-col items-center justify-end h-full" title="{{ $heure }}h : {{ $nombre }} entrée(s)">
                    <div class="w-full bg-emerald-500 rounded-t" style="height: {{ round(100 * $nombre / $maxAffluence) }}%"></div>
                    <span class="text-[10px] text-slate-500 mt-1">{{ $heure }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="grid lg:grid-cols-2 gap-4">
    {{-- Moins actifs --}}
    <section class="bg-white rounded-xl p-4 shadow-sm">
        <h2 class="font-semibold mb-1">Abonnés moins actifs — à relancer</h2>
        <p class="text-xs text-slate-500 mb-3">Abonnement en cours, aucune venue depuis {{ config('salle.kpi.inactif_jours') }} jours</p>
        <table class="w-full text-sm">
            <thead class="text-left text-slate-500"><tr><th class="py-1">Client</th><th>Téléphone</th><th>Dernière venue</th></tr></thead>
            <tbody>
            @forelse($moinsActifs as $client)
                <tr class="border-t">
                    <td class="py-1.5"><a href="{{ route('clients.show', $client) }}" class="text-sky-700 hover:underline">{{ $client->nom_complet }}</a></td>
                    <td>{{ $client->telephone ?? '—' }}</td>
                    <td>{{ $depuis($client->dernier_passage_le) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="py-2 text-slate-400">Aucun abonné inactif 🎉</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    {{-- Expirant bientôt --}}
    <section class="bg-white rounded-xl p-4 shadow-sm">
        <h2 class="font-semibold mb-1">Abonnements qui expirent bientôt</h2>
        <p class="text-xs text-slate-500 mb-3">Dans les {{ config('salle.kpi.expiration_alerte_jours') }} prochains jours, non encore renouvelés</p>
        <table class="w-full text-sm">
            <thead class="text-left text-slate-500"><tr><th class="py-1">Client</th><th>Formule</th><th>Fin</th></tr></thead>
            <tbody>
            @forelse($expirantBientot as $abonnement)
                <tr class="border-t">
                    <td class="py-1.5"><a href="{{ route('clients.show', $abonnement->client) }}" class="text-sky-700 hover:underline">{{ $abonnement->client->nom_complet }}</a></td>
                    <td>{{ $abonnement->formule->nom }}</td>
                    <td>{{ $abonnement->date_fin->format('d/m/Y') }} ({{ $abonnement->joursRestants() }} j)</td>
                </tr>
            @empty
                <tr><td colspan="3" class="py-2 text-slate-400">Aucune échéance proche</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    {{-- Actifs --}}
    <section class="bg-white rounded-xl p-4 shadow-sm">
        <h2 class="font-semibold mb-3">Abonnés les plus actifs</h2>
        <table class="w-full text-sm">
            <thead class="text-left text-slate-500"><tr><th class="py-1">Client</th><th>Venues (30 j)</th><th>Dernière venue</th></tr></thead>
            <tbody>
            @forelse($actifs->take(15) as $client)
                <tr class="border-t">
                    <td class="py-1.5"><a href="{{ route('clients.show', $client) }}" class="text-sky-700 hover:underline">{{ $client->nom_complet }}</a></td>
                    <td>{{ $client->passages_30j }}</td>
                    <td>{{ $depuis($client->dernier_passage_le) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="py-2 text-slate-400">Aucun abonné actif sur la période</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    {{-- Renouvellements --}}
    <section class="bg-white rounded-xl p-4 shadow-sm">
        <h2 class="font-semibold mb-3">Clients qui ont renouvelé ({{ $du->format('d/m') }} – {{ $au->format('d/m') }})</h2>
        <table class="w-full text-sm">
            <thead class="text-left text-slate-500"><tr><th class="py-1">Client</th><th>Formule</th><th>Le</th></tr></thead>
            <tbody>
            @forelse($renouvellements as $abonnement)
                <tr class="border-t">
                    <td class="py-1.5"><a href="{{ route('clients.show', $abonnement->client) }}" class="text-sky-700 hover:underline">{{ $abonnement->client->nom_complet }}</a></td>
                    <td>{{ $abonnement->formule->nom }}</td>
                    <td>{{ $abonnement->created_at->format('d/m/Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="py-2 text-slate-400">Aucun renouvellement sur la période</td></tr>
            @endforelse
            </tbody>
        </table>

        @if($renouvellement['perdus']->isNotEmpty())
            <h3 class="font-semibold mt-4 mb-2 text-sm text-red-700">Non renouvelés ({{ $renouvellement['perdus']->count() }})</h3>
            <ul class="text-sm space-y-1">
                @foreach($renouvellement['perdus'] as $abonnement)
                    <li>{{ $abonnement->client->nom_complet }} — fin le {{ $abonnement->date_fin->format('d/m/Y') }} · {{ $abonnement->client->telephone ?? 'pas de téléphone' }}</li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection
