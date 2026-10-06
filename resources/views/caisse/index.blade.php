@extends('layouts.app')
@section('title', 'Caisse')

@php
    use App\Support\Fcfa;
    use Illuminate\Support\Carbon;
    $ongletClasse = 'flex flex-1 items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold transition aria-selected:bg-brand-500 aria-selected:text-white aria-selected:shadow-sm text-slate-600 hover:text-slate-900';
@endphp

@section('content')
<div class="mb-6">
    <h1 class="text-3xl font-bold tracking-tight text-slate-900">Caisse / Enregistrement</h1>
    <p class="mt-1 text-slate-500">Gérez vos ventes et enregistrements. Le ticket s'imprime à chaque encaissement.</p>
</div>

<div data-tabs="{{ $onglet }}" data-tabs-simple>
    <div class="card mb-6 flex gap-1 p-1.5" role="tablist">
        <button type="button" data-tab="passage" role="tab" class="{{ $ongletClasse }}"><x-icon name="ticket" class="size-5"/> Entrée de passage</button>
        <button type="button" data-tab="abonnement" role="tab" class="{{ $ongletClasse }}"><x-icon name="calendar" class="size-5"/> Abonnement</button>
        <button type="button" data-tab="carnet" role="tab" class="{{ $ongletClasse }}"><x-icon name="tag" class="size-5"/> Carnet Fidélité</button>
        <button type="button" data-tab="renouvellement" role="tab" class="{{ $ongletClasse }}"><x-icon name="refresh" class="size-5"/> Renouvellement
            @if($aRenouveler->isNotEmpty())<span class="rounded-full bg-orange-500 px-1.5 text-[10px] text-white">{{ $aRenouveler->count() }}</span>@endif
        </button>
    </div>

    {{-- ============ PASSAGE ============ --}}
    <form data-panel="passage" data-calcul-passage data-prix="{{ $tarifJournalier }}" method="POST" action="{{ route('caisse.journalier') }}" class="grid gap-6 xl:grid-cols-3">
        @csrf
        <div class="card p-6 xl:col-span-2">
            <h2 class="mb-6 flex items-center gap-3 text-lg font-semibold text-slate-900">
                <span class="pastille size-9 bg-emerald-50 text-emerald-600"><x-icon name="ticket" class="size-5"/></span> Passage
            </h2>
            <div class="space-y-5">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <span class="label">Type de ticket</span>
                        <p class="input bg-slate-50">Entrée simple (1 jour)</p>
                    </div>
                    <div>
                        <label for="quantite" class="label">Quantité</label>
                        <div class="flex items-center rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
                            <button type="button" data-quantite-moins class="px-3.5 py-2.5 text-slate-600 hover:text-brand-600" aria-label="Moins"><x-icon name="minus" class="size-4"/></button>
                            <input id="quantite" name="quantite" type="number" min="1" max="10" value="{{ old('quantite', 1) }}" data-quantite
                                   class="w-full border-0 bg-transparent py-2.5 text-center text-sm font-semibold focus:outline-none">
                            <button type="button" data-quantite-plus class="px-3.5 py-2.5 text-slate-600 hover:text-brand-600" aria-label="Plus"><x-icon name="plus" class="size-4"/></button>
                        </div>
                    </div>
                    <div>
                        <span class="label">Prix unitaire</span>
                        <p class="input bg-slate-50">{{ Fcfa::format($tarifJournalier) }}</p>
                    </div>
                </div>

                @include('caisse._modes', ['prefixe' => 'j'])

                <div>
                    <span class="label">Client <span class="font-normal text-slate-400">(optionnel)</span></span>
                    @include('caisse._recherche', ['requis' => false, 'preselection' => null])
                    <details class="mt-2 text-sm" @if(old('nom') || old('telephone')) open @endif>
                        <summary class="cursor-pointer text-brand-600 hover:underline">Nouveau client ? Saisir son nom et son téléphone</summary>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <input type="text" name="nom" value="{{ old('nom') }}" maxlength="100" class="input" placeholder="Nom">
                            <input type="tel" name="telephone" value="{{ old('telephone') }}" maxlength="20" class="input" placeholder="Téléphone">
                        </div>
                    </details>
                </div>

                <div>
                    <span class="label">Montant total</span>
                    <p data-total class="input bg-slate-50 py-3 text-lg font-bold">{{ Fcfa::format($tarifJournalier) }}</p>
                </div>

                <button type="submit" class="btn-primary btn-lg w-full"><x-icon name="printer" class="size-5"/> Valider la vente</button>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card p-6">
                <h3 class="mb-4 font-semibold text-slate-900">Récapitulatif</h3>
                <div class="flex justify-between text-sm text-slate-600"><span>Entrée simple (1 jour) × <span data-recap-quantite>1</span></span><span data-total-court>{{ Fcfa::format($tarifJournalier) }}</span></div>
                <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-4">
                    <span class="font-semibold">Total</span><span data-total-court class="text-xl font-bold text-slate-900">{{ Fcfa::format($tarifJournalier) }}</span>
                </div>
            </div>
            <div class="card flex gap-3 bg-sky-50/60 p-5 ring-sky-100">
                <x-icon name="user" class="size-6 shrink-0 text-sky-600"/>
                <div class="text-sm">
                    <p class="font-semibold text-slate-900">Besoin d'un abonnement ?</p>
                    <button type="button" data-tab-aller="abonnement" class="link mt-1 inline-flex items-center gap-1">Aller à l'onglet Abonnement <x-icon name="arrow-right" class="size-4"/></button>
                </div>
            </div>
            @include('caisse._resume')
        </div>
    </form>

    {{-- ============ ABONNEMENT ============ --}}
    <form data-panel="abonnement" data-recap-source method="POST" action="{{ route('caisse.abonnement') }}" class="grid gap-6 xl:grid-cols-3" hidden>
        @csrf
        <div class="card p-6 xl:col-span-2">
            <h2 class="mb-6 flex items-center gap-3 text-lg font-semibold text-slate-900">
                <span class="pastille size-9 bg-sky-50 text-sky-600"><x-icon name="calendar" class="size-5"/></span> Abonnement
            </h2>
            <div class="space-y-5">
                <div>
                    <span class="label">Client *</span>
                    @include('caisse._recherche', ['requis' => true, 'preselection' => $clientPreselectionne])
                    <p class="mt-2 text-xs text-slate-500">Pas encore de fiche ? <a href="{{ route('clients.index', ['nouveau' => 1]) }}" class="link">Créer le client</a>, vous revenez ici ensuite.</p>
                </div>

                <fieldset>
                    <legend class="label">Formule *</legend>
                    @if($formules->isEmpty())
                        <p class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">Aucune formule active. Le responsable doit en créer dans « Formules &amp; tarifs ».</p>
                    @endif
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach($formules as $formule)
                            <label class="choice">
                                <input type="radio" name="formule_id" value="{{ $formule->id }}" required
                                       data-prix="{{ Fcfa::format($formule->prix) }}" data-nom="{{ $formule->nom }} ({{ $formule->duree_jours }} jours)"
                                       @checked((int) old('formule_id', $loop->first ? $formule->id : 0) === $formule->id)>
                                <span class="text-sm font-semibold text-slate-900">{{ $formule->nom }}</span>
                                <span class="text-xs text-slate-500">{{ $formule->resume() }}</span>
                                <span class="mt-3 whitespace-nowrap text-base font-bold text-brand-600">{{ Fcfa::format($formule->prix) }}</span>
                                @if($formule->avantages())
                                    {{-- Ce que la caissière remet au client (serviettes, coaching…) --}}
                                    <ul class="mt-2 space-y-0.5 text-xs text-slate-500">
                                        @foreach($formule->avantages() as $avantage)<li>✓ {{ $avantage }}</li>@endforeach
                                    </ul>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                @include('caisse._modes', ['prefixe' => 'a'])

                <button type="submit" class="btn-primary btn-lg w-full"><x-icon name="printer" class="size-5"/> Valider l'abonnement</button>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card p-6">
                <h3 class="mb-4 font-semibold text-slate-900">Récapitulatif</h3>
                <div class="flex justify-between gap-3 text-sm text-slate-600"><span data-recap-nom>—</span><span data-recap-montant>—</span></div>
                <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-4">
                    <span class="font-semibold">Total</span><span data-recap-montant class="text-xl font-bold text-slate-900">—</span>
                </div>
                <p class="mt-4 text-xs text-slate-500">Renouvellement anticipé : le nouvel abonnement démarre le lendemain de la fin de l'actuel. Aucun jour perdu.</p>
            </div>
            @include('caisse._resume')
        </div>
    </form>

    {{-- ============ CARNET FIDÉLITÉ ============ --}}
    <form data-panel="carnet" data-calcul-passage data-prix="{{ $tarifFidelite }}" method="POST" action="{{ route('caisse.carnet') }}" class="grid gap-6 xl:grid-cols-3" hidden>
        @csrf
        <div class="card p-6 xl:col-span-2">
            <h2 class="mb-6 flex items-center gap-3 text-lg font-semibold text-slate-900">
                <span class="pastille size-9 bg-amber-50 text-amber-600"><x-icon name="tag" class="size-5"/></span> Carnet Fidélité
            </h2>
            <div class="space-y-5">
                <div>
                    <span class="label">Client *</span>
                    @include('caisse._recherche', ['requis' => true, 'preselection' => null])
                    <p class="mt-2 text-xs text-slate-500">Le client doit avoir un n° d'empreinte : chaque arrivée à la pointeuse décompte une séance.</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="seances" class="label">Nombre de séances (minimum {{ $carnetMin }})</label>
                        <div class="flex items-center rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
                            <button type="button" data-quantite-moins class="px-3.5 py-2.5 text-slate-600 hover:text-brand-600" aria-label="Moins"><x-icon name="minus" class="size-4"/></button>
                            <input id="seances" name="seances" type="number" min="{{ $carnetMin }}" max="100" value="{{ old('seances', $carnetMin) }}" data-quantite
                                   class="w-full border-0 bg-transparent py-2.5 text-center text-sm font-semibold focus:outline-none">
                            <button type="button" data-quantite-plus class="px-3.5 py-2.5 text-slate-600 hover:text-brand-600" aria-label="Plus"><x-icon name="plus" class="size-4"/></button>
                        </div>
                    </div>
                    <div>
                        <span class="label">Prix par séance</span>
                        <p class="input bg-slate-50">{{ Fcfa::format($tarifFidelite) }}</p>
                    </div>
                </div>

                @include('caisse._modes', ['prefixe' => 'c'])

                <div>
                    <span class="label">Montant total</span>
                    <p data-total class="input bg-slate-50 py-3 text-lg font-bold">{{ Fcfa::format($tarifFidelite * $carnetMin) }}</p>
                </div>

                <button type="submit" class="btn-primary btn-lg w-full"><x-icon name="printer" class="size-5"/> Valider le carnet</button>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card p-6">
                <h3 class="mb-4 font-semibold text-slate-900">Récapitulatif</h3>
                <div class="flex justify-between text-sm text-slate-600"><span>Séance Fidélité × <span data-recap-quantite>{{ $carnetMin }}</span></span><span data-total-court>{{ Fcfa::format($tarifFidelite * $carnetMin) }}</span></div>
                <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-4">
                    <span class="font-semibold">Total</span><span data-total-court class="text-xl font-bold text-slate-900">{{ Fcfa::format($tarifFidelite * $carnetMin) }}</span>
                </div>
                <p class="mt-4 text-xs text-slate-500">Une séance est décomptée à chaque arrivée (le départ ne compte pas). Quand le carnet est vide, la pointeuse refuse le client.</p>
            </div>
            @include('caisse._resume')
        </div>
    </form>

    {{-- ============ RENOUVELLEMENT ============ --}}
    <section data-panel="renouvellement" class="card overflow-hidden" hidden>
        <div class="card-header">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Abonnements à renouveler</h2>
                <p class="text-xs text-slate-500">Fin dans les 7 prochains jours ou depuis moins de 30 jours. Un clic ouvre l'onglet Abonnement avec le client.</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Client</th><th>Téléphone</th><th>Fin des droits</th><th>Statut</th><th></th></tr></thead>
                <tbody>
                @forelse($aRenouveler as $c)
                    @php $fin = Carbon::parse($c->fin); $jours = (int) today()->diffInDays($fin, false); @endphp
                    <tr>
                        <td><a href="{{ route('clients.show', $c) }}" class="flex items-center gap-2 font-medium text-slate-900 hover:text-brand-600"><x-avatar :client="$c" size="size-8" text="text-xs"/> {{ $c->nom_complet }}</a></td>
                        <td>{{ $c->telephone ?? '—' }}</td>
                        <td>{{ $fin->format('d/m/Y') }}</td>
                        <td>
                            @if($jours >= 0)<span class="pill-amber"><x-icon name="clock" class="size-3"/> {{ $jours === 0 ? 'Expire aujourd\'hui' : 'Expire dans '.$jours.' j' }}</span>
                            @else<span class="pill-red"><x-icon name="x" class="size-3"/> Expiré depuis {{ -$jours }} j</span>@endif
                        </td>
                        <td class="text-right"><a href="{{ route('caisse.index', ['client_id' => $c->id]) }}" class="btn-primary btn-sm"><x-icon name="refresh" class="size-3.5"/> Renouveler</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-12 text-center text-slate-400">Aucun abonnement à renouveler pour le moment.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
