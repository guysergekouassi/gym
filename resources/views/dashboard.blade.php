@extends('layouts.app')
@section('title', 'Tableau de bord')

@php
    use App\Models\Client;
    use App\Services\KpiService;
    use App\Support\Fcfa;
    use Illuminate\Support\Carbon;

    $couleurs = ['#0f9960', '#2563eb', '#d97706', '#9333ea'];
    $depuis = fn ($date) => $date ? Carbon::parse($date)->diffForHumans() : 'Jamais venu';
    $totalTop = max(1, $topFormules->sum('en_cours'));
    $styleActivite = [
        'client' => ['user-plus', 'bg-emerald-50 text-emerald-600'],
        'abonnement' => ['calendar', 'bg-sky-50 text-sky-600'],
        'renouvellement' => ['refresh', 'bg-violet-50 text-violet-600'],
        'passage' => ['ticket', 'bg-orange-50 text-orange-500'],
    ];
@endphp

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-3xl font-bold tracking-tight text-slate-900">Tableau de bord</h1>
        <p class="mt-1 text-slate-500">Vue d'ensemble de votre salle de sport</p>
    </div>
    <form method="GET" class="card flex items-center gap-2 px-3 py-2" title="Période utilisée pour le taux de renouvellement">
        <x-icon name="calendar" class="size-5 text-slate-500"/>
        <input type="date" name="du" value="{{ $du->toDateString() }}" class="border-0 bg-transparent p-1 text-sm focus:outline-none" aria-label="Du">
        <x-icon name="arrow-right" class="size-4 text-slate-400"/>
        <input type="date" name="au" value="{{ $au->toDateString() }}" class="border-0 bg-transparent p-1 text-sm focus:outline-none" aria-label="Au">
        <button type="submit" class="btn-primary btn-sm">OK</button>
    </form>
</div>

{{-- Chiffres clés --}}
<div class="mb-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-5">
    <x-kpi label="Clients inscrits" :value="$clientsTotal" icon="users" tone="green"
           :variation="KpiService::variation($clientsCeMois, $clientsMoisDernier)" reference="nouveaux, vs mois dernier"/>
    <x-kpi label="Revenus du jour" :value="Fcfa::format($aujourdhui['recette'])" icon="cash" tone="purple"
           :variation="KpiService::variation($aujourdhui['recette'], $hier['recette'])"/>
    <x-kpi label="Abonnements actifs" :value="$abonnementsEnCours" icon="calendar" tone="blue"
           :hint="$actifs->count().' venus cette semaine · '.$moinsActifs->count().' à relancer'"/>
    <x-kpi label="Entrées du jour" :value="$aujourdhui['entrees']" icon="user" tone="orange"
           :variation="KpiService::variation($aujourdhui['entrees'], $hier['entrees'])"/>
    <x-kpi label="Tickets vendus" :value="$aujourdhui['tickets']" icon="ticket" tone="purple"
           :variation="KpiService::variation($aujourdhui['tickets'], $hier['tickets'])"/>
</div>

<div class="mb-6 grid gap-6 xl:grid-cols-12">
    <section class="card p-6 xl:col-span-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-slate-900">Évolution des revenus <span class="text-sm font-normal text-slate-500">(7 derniers jours)</span></h2>
            <div class="flex gap-4 text-xs text-slate-600">
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-serie-1"></span> Abonnements</span>
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-serie-2"></span> Passages</span>
            </div>
        </div>
        <x-chart.lignes class="mt-4" titre="Revenus des 7 derniers jours"
            :etiquettes="array_map(fn ($r) => $r['jour']->format('d/m'), $recettes)"
            :series="[
                ['nom' => 'Abonnements', 'couleur' => $couleurs[0], 'valeurs' => array_column($recettes, 'abonnement')],
                ['nom' => 'Passages', 'couleur' => $couleurs[1], 'valeurs' => array_column($recettes, 'journalier')],
            ]"/>
    </section>

    <section class="card p-6 xl:col-span-4">
        <h2 class="mb-5 text-lg font-semibold text-slate-900">Répartition des clients</h2>
        <x-chart.anneau :centre="$clientsTotal" sous-titre="clients"
            :parts="collect($repartition)->values()->map(fn ($p, $i) => $p + ['couleur' => $couleurs[$i]])->all()"/>
    </section>

    <section class="card xl:col-span-3">
        <div class="card-header"><h2 class="text-lg font-semibold text-slate-900">Activité récente</h2></div>
        <ul class="divide-y divide-slate-100">
            @forelse($activite as $a)
                @php [$icone, $teinte] = $styleActivite[$a['type']]; @endphp
                <li class="flex items-center gap-3 px-5 py-3">
                    <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-full {{ $teinte }}"><x-icon :name="$icone" class="size-4"/></span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-slate-900">{{ $a['titre'] }}</span>
                        <span class="block truncate text-xs text-slate-500">{{ $a['detail'] }}</span>
                    </span>
                    <span class="text-xs text-slate-500">{{ $a['quand']->isToday() ? $a['quand']->format('H:i') : $a['quand']->format('d/m') }}</span>
                </li>
            @empty
                <li class="px-5 py-10 text-center text-sm text-slate-400">Aucune activité pour l'instant.</li>
            @endforelse
        </ul>
    </section>
</div>

<div class="mb-6 grid gap-6 xl:grid-cols-3">
    <section class="card overflow-hidden">
        <div class="card-header"><h2 class="text-lg font-semibold text-slate-900">Top des formules</h2><a href="{{ route('admin.formules.index') }}" class="link text-xs">Voir tout</a></div>
        <table class="table">
            <thead><tr><th>#</th><th>Formule</th><th>Abonnés en cours</th></tr></thead>
            <tbody>
            @forelse($topFormules as $i => $f)
                @php $pct = round(100 * $f->en_cours / $totalTop); @endphp
                <tr>
                    <td class="text-slate-500">{{ $i + 1 }}</td>
                    <td class="font-medium">{{ $f->nom }}</td>
                    <td>
                        <div class="flex items-center gap-3">
                            <span class="w-6 font-semibold">{{ $f->en_cours }}</span>
                            <span class="w-9 text-xs text-slate-500">{{ $pct }} %</span>
                            <span class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100"><span class="block h-full rounded-full" style="width: {{ $pct }}%; background: {{ $couleurs[$i % 4] }}"></span></span>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="py-8 text-center text-slate-400">Aucune formule</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="card overflow-hidden">
        <div class="card-header"><h2 class="text-lg font-semibold text-slate-900">Derniers clients</h2><a href="{{ route('clients.index') }}" class="link text-xs">Voir tout</a></div>
        <table class="table">
            <thead><tr><th>Nom</th><th>Type</th><th>Inscrit le</th></tr></thead>
            <tbody>
            @forelse($derniersClients as $c)
                <tr>
                    <td><a href="{{ route('clients.show', $c) }}" class="flex items-center gap-2 font-medium text-slate-900 hover:text-brand-600"><x-avatar :client="$c" size="size-7" text="text-[10px]"/> {{ $c->nom_complet }}</a></td>
                    <td><span class="{{ $c->type === Client::TYPE_ABONNE ? 'pill-green' : 'pill-blue' }}">{{ Client::TYPES[$c->type] }}</span></td>
                    <td class="text-slate-600">{{ $c->created_at->format('d/m/Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="py-8 text-center text-slate-400">Aucun client</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="card overflow-hidden">
        <div class="card-header">
            <h2 class="text-lg font-semibold text-slate-900">Prochains renouvellements</h2>
            <a href="{{ route('caisse.index', ['onglet' => 'renouvellement']) }}" class="link text-xs">Voir tout</a>
        </div>
        <table class="table">
            <thead><tr><th>Client</th><th>Formule</th><th>Fin</th></tr></thead>
            <tbody>
            @forelse($expirantBientot->take(6) as $abonnement)
                <tr>
                    <td><a href="{{ route('clients.show', $abonnement->client) }}" class="font-medium text-slate-900 hover:text-brand-600">{{ $abonnement->client->nom_complet }}</a></td>
                    <td class="text-slate-600">{{ $abonnement->formule->nom }}</td>
                    <td><span class="{{ $abonnement->joursRestants() <= 2 ? 'pill-red' : 'pill-amber' }}"><x-icon name="calendar" class="size-3"/> {{ $abonnement->date_fin->format('d/m/Y') }}</span></td>
                </tr>
            @empty
                <tr><td colspan="3" class="py-8 text-center text-slate-400">Aucune échéance dans les 7 jours</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
</div>

<div class="grid gap-6 xl:grid-cols-3">
    <section class="card overflow-hidden xl:col-span-2">
        <div class="card-header">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Abonnés à relancer</h2>
                <p class="text-xs text-slate-500">Ils paient mais ne sont pas venus depuis {{ config('salle.kpi.inactif_jours') }} jours : un appel les fait revenir.</p>
            </div>
            <span class="pill-amber">{{ $moinsActifs->count() }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Client</th><th>Téléphone</th><th>Dernière venue</th></tr></thead>
                <tbody>
                @forelse($moinsActifs->take(8) as $client)
                    <tr>
                        <td><a href="{{ route('clients.show', $client) }}" class="font-medium text-slate-900 hover:text-brand-600">{{ $client->nom_complet }}</a></td>
                        <td>{{ $client->telephone ?? '—' }}</td>
                        <td class="text-slate-500">{{ $depuis($client->dernier_passage_le) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-8 text-center text-slate-400">Tous les abonnés viennent régulièrement.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card p-6">
        <h2 class="text-lg font-semibold text-slate-900">Fidélité</h2>
        <p class="text-xs text-slate-500">Du {{ $du->format('d/m/Y') }} au {{ $au->format('d/m/Y') }}</p>
        <p class="mt-5 text-5xl font-bold tracking-tight text-slate-900">{{ $renouvellement['taux'] !== null ? $renouvellement['taux'].' %' : '—' }}</p>
        <p class="mt-1 text-sm text-slate-600">des abonnements arrivés à échéance ont été renouvelés</p>
        <dl class="mt-5 space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-slate-600">Renouvelés</dt><dd class="font-semibold">{{ $renouvellement['renouveles'] }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-600">Non renouvelés</dt><dd class="font-semibold">{{ $renouvellement['perdus']->count() }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-600">Encore dans le délai de {{ config('salle.kpi.renouvellement_delai_jours') }} j</dt><dd class="font-semibold">{{ $renouvellement['en_attente']->count() }}</dd></div>
        </dl>
    </section>
</div>
@endsection
