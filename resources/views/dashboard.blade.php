@extends('layouts.app')
@section('title', 'Tableau de bord')

@php
    use App\Models\Client;
    use App\Services\KpiService;
    use App\Support\Fcfa;
    use Illuminate\Support\Carbon;

    $couleurs = ['#0f9960', '#2563eb', '#d97706', '#94a3b8']; // 2 formules, passages, autres (gris) — palette validée daltonisme
    $totalTop = max(1, $topFormules->sum('en_cours'));
    $styleActivite = [
        'client' => ['user-plus', 'bg-emerald-50 text-emerald-600'],
        'abonnement' => ['calendar', 'bg-sky-50 text-sky-600'],
        'renouvellement' => ['refresh', 'bg-violet-50 text-violet-600'],
        'passage' => ['ticket', 'bg-orange-50 text-orange-500'],
    ];
    $suffixe = $periode->suffixe();
    $ref = $periode->reference();
@endphp

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-3xl font-bold tracking-tight text-slate-900">Tableau de bord</h1>
        <p class="mt-1 text-slate-500">Vue d'ensemble de votre salle de sport</p>
    </div>
    <x-filtre-periode :periode="$periode" :annees="$annees"/>
</div>

{{-- Chiffres clés de la période choisie --}}
<div class="mb-5 grid gap-4 sm:grid-cols-2 md:grid-cols-3 {{ $caMois ? 'xl:grid-cols-6' : 'lg:grid-cols-5' }} 2xl:gap-5">
    <x-kpi label="Clients inscrits" :value="$chiffres['clients_inscrits']" icon="users" tone="green"
           :variation="KpiService::variation($chiffres['clients_inscrits'], $avant['clients_inscrits'])" :reference="$ref" :comparable="true"
           :hint="$clientsTotal.' clients au total'"/>
    <x-kpi :label="'Chiffre d\'affaires '.$suffixe" :value="Fcfa::format($chiffres['recette'])" icon="cash" tone="purple"
           :variation="KpiService::variation($chiffres['recette'], $avant['recette'])" :reference="$ref" :comparable="true"/>
    @if($caMois)
        <x-kpi :label="$caMois['libelle']" :value="Fcfa::format($caMois['montant'])" icon="chart" tone="blue"
               :variation="KpiService::variation($caMois['montant'], $caMois['precedent'])" :reference="$caMois['reference']" :comparable="true"/>
    @endif
    <x-kpi label="Abonnements actifs" :value="$chiffres['abonnements_actifs']" icon="calendar" tone="blue"
           :variation="KpiService::variation($chiffres['abonnements_actifs'], $avant['abonnements_actifs'])" :reference="$ref" :comparable="true"/>
    <x-kpi :label="'Entrées '.$suffixe" :value="$chiffres['entrees']" icon="user" tone="orange"
           :variation="KpiService::variation($chiffres['entrees'], $avant['entrees'])" :reference="$ref" :comparable="true"/>
    <x-kpi :label="'Tickets vendus '.($periode->granularite === 'jour' ? '' : $suffixe)" :value="$chiffres['tickets']" icon="ticket" tone="purple"
           :variation="KpiService::variation($chiffres['tickets'], $avant['tickets'])" :reference="$ref" :comparable="true"/>
</div>

<div class="mb-5 grid gap-5 lg:grid-cols-12">
    <section class="card flex flex-col p-5 lg:col-span-5 2xl:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-slate-900 2xl:text-lg">Évolution des revenus <span class="text-sm font-normal text-slate-500">({{ $serie['titre'] }})</span></h2>
            <div class="flex gap-4 text-xs text-slate-600">
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-serie-1"></span> Abonnements</span>
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-serie-2"></span> Passages</span>
            </div>
        </div>
        <x-chart.lignes class="my-auto pt-3" :largeur="520" :hauteur="300" titre="Évolution des revenus"
            :etiquettes="$serie['etiquettes']"
            :series="[
                ['nom' => 'Abonnements', 'couleur' => '#0f9960', 'valeurs' => $serie['abonnement']],
                ['nom' => 'Passages', 'couleur' => '#2563eb', 'valeurs' => $serie['journalier']],
            ]"/>
    </section>

    <section class="card p-5 lg:col-span-4 2xl:p-6">
        <h2 class="mb-5 text-base font-semibold text-slate-900 2xl:text-lg">Répartition des clients</h2>
        <x-chart.anneau :centre="$clientsTotal" sous-titre="clients" :afficher-valeurs="false"
            :parts="collect($repartition)->values()->map(fn ($p, $i) => $p + ['couleur' => $couleurs[$i] ?? '#94a3b8'])->all()"/>
    </section>

    <section class="card lg:col-span-3">
        <div class="card-header"><h2 class="text-base font-semibold text-slate-900 2xl:text-lg">Activité récente</h2><a href="{{ route('admin.paiements.index') }}" class="link whitespace-nowrap text-xs">Voir tout</a></div>
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

<div class="grid gap-5 lg:grid-cols-3 [&_.table_td]:px-3 [&_.table_th]:px-3 max-2xl:[&_.table]:text-[13px]">
    <section class="card overflow-hidden">
        <div class="card-header"><h2 class="text-base font-semibold text-slate-900 2xl:text-lg">Top 5 des abonnements</h2><a href="{{ route('admin.formules.index') }}" class="link whitespace-nowrap text-xs">Voir tout</a></div>
        <table class="table">
            <thead><tr><th>#</th><th>Formule</th><th>Nombre de clients</th></tr></thead>
            <tbody>
            @forelse($topFormules as $i => $f)
                @php $pct = round(100 * $f->en_cours / $totalTop); @endphp
                <tr>
                    <td class="text-slate-500">{{ $i + 1 }}</td>
                    <td class="font-medium">{{ $f->nom }}</td>
                    <td>
                        <div class="flex items-center gap-3">
                            <span class="w-7 font-semibold">{{ $f->en_cours }}</span>
                            <span class="w-10 text-xs text-slate-500">{{ $pct }} %</span>
                            <span class="h-2 min-w-12 flex-1 overflow-hidden rounded-full bg-slate-100"><span class="block h-full rounded-full" style="width: {{ $pct }}%; background: {{ ['#0f9960', '#2563eb', '#d97706', '#9333ea', '#94a3b8'][$i] ?? '#94a3b8' }}"></span></span>
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
        <div class="card-header"><h2 class="text-base font-semibold text-slate-900 2xl:text-lg">Derniers clients</h2><a href="{{ route('clients.index') }}" class="link whitespace-nowrap text-xs">Voir tout</a></div>
        <div class="overflow-x-auto">
            <table class="table [&_td]:px-3 [&_th]:px-3">
                <thead><tr><th>Nom</th><th class="max-2xl:hidden">Type</th><th>Date</th><th>Statut</th></tr></thead>
                <tbody>
                @forelse($derniersClients as $c)
                    @php $enRegle = $c->fin_droits && Carbon::parse($c->fin_droits)->gte(today()); @endphp
                    <tr>
                        <td><a href="{{ route('clients.show', $c) }}" class="flex max-w-40 items-center gap-2 font-medium text-slate-900 hover:text-brand-600" title="{{ $c->nom_complet }}"><x-icon name="user" class="size-5 shrink-0 text-slate-400"/> <span class="truncate">{{ $c->nom_complet }}</span></a></td>
                        <td class="text-slate-600 max-2xl:hidden">{{ $c->type === Client::TYPE_ABONNE ? 'Abonnement' : 'Passage' }}</td>
                        <td class="whitespace-nowrap text-slate-600">{{ $c->created_at->format('d/m/y') }}</td>
                        <td>
                            @if($enRegle)<span class="pill-green"><x-icon name="check" class="size-3"/> Actif</span>
                            @elseif($c->type === Client::TYPE_JOURNALIER)<span class="pill-blue">Validé</span>
                            @else<span class="pill-red">Expiré</span>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-slate-400">Aucun client</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card overflow-hidden">
        <div class="card-header">
            <h2 class="text-base font-semibold text-slate-900 2xl:text-lg">Prochains renouvellements</h2>
            <a href="{{ route('caisse.index', ['onglet' => 'renouvellement']) }}" class="link whitespace-nowrap text-xs">Voir tout</a>
        </div>
        <div class="overflow-x-auto"><table class="table">
            <thead><tr><th>Client</th><th>Formule</th><th>Date</th></tr></thead>
            <tbody>
            @forelse($expirantBientot->take(5) as $abonnement)
                <tr>
                    <td><a href="{{ route('clients.show', $abonnement->client) }}" class="flex items-center gap-2 whitespace-nowrap font-medium text-slate-900 hover:text-brand-600"><x-icon name="user" class="size-5 shrink-0 text-slate-400"/> {{ $abonnement->client->nom_complet }}</a></td>
                    <td class="text-slate-600">{{ $abonnement->formule->nom }}</td>
                    <td class="whitespace-nowrap"><span class="inline-flex items-center gap-1.5 {{ $abonnement->joursRestants() <= 2 ? 'text-red-600' : 'text-slate-700' }}"><x-icon name="calendar" class="size-4 text-slate-400 max-2xl:hidden"/> {{ $abonnement->date_fin->format('d/m/y') }}</span></td>
                </tr>
            @empty
                <tr><td colspan="3" class="py-8 text-center text-slate-400">Aucune échéance dans les 7 jours</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>
</div>
@endsection
