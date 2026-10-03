@extends('layouts.app')
@section('title', 'Caisse')

@php
    use App\Models\Paiement;
    use App\Support\Fcfa;
@endphp

@section('content')
<x-page-header title="Caisse" subtitle="Bonjour {{ auth()->user()->name }} — encaissez un passage ou un abonnement, le ticket s'imprime automatiquement.">
    <a href="{{ route('clients.create') }}" class="btn-light"><x-icon name="user-plus" class="size-4"/> Nouveau client</a>
</x-page-header>

<div class="grid gap-6 xl:grid-cols-3">
    {{-- Encaissement --}}
    <div class="xl:col-span-2" data-tabs="{{ $onglet }}">
        <div class="mb-4 inline-flex rounded-2xl bg-slate-200/70 p-1.5" role="tablist">
            <button type="button" data-tab="passage" role="tab" class="inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold transition">
                <x-icon name="bolt" class="size-5"/> Passage (séance)
            </button>
            <button type="button" data-tab="abonnement" role="tab" class="inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold transition">
                <x-icon name="card" class="size-5"/> Abonnement
            </button>
        </div>

        {{-- Passage journalier --}}
        <form data-panel="passage" method="POST" action="{{ route('caisse.journalier') }}" class="card">
            @csrf
            <div class="card-header">
                <div>
                    <h2 class="card-title">Entrée journalière</h2>
                    <p class="text-xs text-slate-500">Le client entre tout de suite. Identification facultative.</p>
                </div>
                <span class="pill-brand text-sm">Tarif : {{ Fcfa::format($tarifJournalier) }}</span>
            </div>
            <div class="card-body space-y-5">
                @include('caisse._recherche', ['requis' => false, 'preselection' => null])

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="j-nom" class="label">Nom <span class="font-normal text-slate-400">(nouveau client)</span></label>
                        <input id="j-nom" type="text" name="nom" value="{{ old('nom') }}" maxlength="100" class="input">
                    </div>
                    <div>
                        <label for="j-tel" class="label">Téléphone</label>
                        <input id="j-tel" type="tel" name="telephone" value="{{ old('telephone') }}" maxlength="20" class="input" placeholder="07 00 00 00 00">
                    </div>
                </div>

                @include('caisse._modes', ['prefixe' => 'j'])
            </div>
            <div class="flex flex-wrap items-center justify-between gap-4 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-5 py-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">À encaisser</p>
                    <p class="text-3xl font-extrabold text-slate-900">{{ Fcfa::format($tarifJournalier) }}</p>
                </div>
                <button type="submit" class="btn-primary btn-lg"><x-icon name="printer" class="size-5"/> Encaisser &amp; imprimer le ticket</button>
            </div>
        </form>

        {{-- Abonnement --}}
        <form data-panel="abonnement" data-recap-source method="POST" action="{{ route('caisse.abonnement') }}" class="card" hidden>
            @csrf
            <div class="card-header">
                <div>
                    <h2 class="card-title">Abonnement / renouvellement</h2>
                    <p class="text-xs text-slate-500">Renouvellement anticipé : le nouvel abonnement démarre le lendemain de la fin de l'actuel, aucun jour perdu.</p>
                </div>
            </div>
            <div class="card-body space-y-5">
                @include('caisse._recherche', ['requis' => true, 'preselection' => $clientPreselectionne])
                <p class="-mt-3 text-xs text-slate-500">Client pas encore enregistré ? <a href="{{ route('clients.create', ['apres' => 'abonner']) }}" class="link">Créez sa fiche</a>, vous revenez ici ensuite.</p>

                <fieldset>
                    <legend class="label">Formule</legend>
                    @if($formules->isEmpty())
                        <p class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">Aucune formule active. Le responsable doit en créer dans « Formules &amp; tarifs ».</p>
                    @endif
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach($formules as $formule)
                            <label class="choice">
                                <input type="radio" name="formule_id" value="{{ $formule->id }}" data-prix="{{ Fcfa::format($formule->prix) }}" required
                                       @checked((int) old('formule_id', $loop->first ? $formule->id : 0) === $formule->id)>
                                <span class="text-sm font-semibold text-slate-900">{{ $formule->nom }}</span>
                                <span class="text-xs text-slate-500">{{ $formule->duree_jours }} jours</span>
                                <span class="mt-3 whitespace-nowrap text-base font-extrabold text-brand-600">{{ Fcfa::format($formule->prix) }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                @include('caisse._modes', ['prefixe' => 'a'])
            </div>
            <div class="flex flex-wrap items-center justify-between gap-4 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-5 py-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">À encaisser</p>
                    <p data-recap-montant class="text-3xl font-extrabold text-slate-900">—</p>
                </div>
                <button type="submit" class="btn-dark btn-lg"><x-icon name="printer" class="size-5"/> Encaisser l'abonnement</button>
            </div>
        </form>
    </div>

    {{-- Résumé de la journée --}}
    <div class="space-y-6">
        <div class="card overflow-hidden">
            <div class="bg-gradient-to-br from-ink-900 to-ink-800 p-5 text-white">
                <p class="text-sm text-slate-300">{{ auth()->user()->isAdmin() ? 'Caisse du jour (tous postes)' : 'Ma caisse aujourd\'hui' }}</p>
                <p class="mt-1 text-3xl font-extrabold">{{ Fcfa::format($totalJour) }}</p>
                <p class="mt-1 text-sm text-slate-400">{{ $nombreJour }} encaissement(s)</p>
            </div>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse($parMode as $mode => $montant)
                    <li class="flex justify-between px-5 py-2.5"><span class="text-slate-600">{{ Paiement::MODES[$mode] ?? $mode }}</span><span class="font-semibold">{{ Fcfa::format($montant) }}</span></li>
                @empty
                    <li class="px-5 py-3 text-slate-400">Aucun encaissement pour l'instant.</li>
                @endforelse
            </ul>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Derniers tickets</h2></div>
            <ul class="divide-y divide-slate-100">
                @forelse($paiements as $paiement)
                    <li class="flex items-center gap-3 px-5 py-3 {{ $paiement->estAnnule() ? 'opacity-50' : '' }}">
                        <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl {{ $paiement->abonnement_id ? 'bg-ink-900 text-white' : 'bg-brand-50 text-brand-600' }}">
                            <x-icon :name="$paiement->abonnement_id ? 'card' : 'bolt'" class="size-4"/>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold {{ $paiement->estAnnule() ? 'line-through' : '' }}">{{ $paiement->client?->nom_complet ?? 'Client anonyme' }}</p>
                            <p class="truncate text-xs text-slate-500">
                                {{ $paiement->created_at->format('H:i') }} ·
                                {{ $paiement->abonnement ? $paiement->abonnement->formule->nom : 'Passage' }} ·
                                <a href="{{ route('recus.show', $paiement) }}" class="link">{{ $paiement->numero_recu }}</a>
                            </p>
                        </div>
                        <span class="text-sm font-bold {{ $paiement->estAnnule() ? 'line-through' : '' }}">{{ Fcfa::format($paiement->montant) }}</span>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-sm text-slate-400">Aucun ticket aujourd'hui.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
