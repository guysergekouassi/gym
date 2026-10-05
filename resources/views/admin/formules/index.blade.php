@extends('layouts.app')
@section('title', 'Formules & tarifs')

@php use App\Support\Fcfa; @endphp

@section('content')
<x-page-header title="Formules & tarifs" subtitle="Les prix modifiés s'appliquent aux prochaines ventes uniquement ; les abonnements déjà vendus ne changent pas."/>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <div class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Abonnements</h2></div>
            <div class="divide-y divide-slate-100">
                @forelse($formules as $formule)
                    <form method="POST" action="{{ route('admin.formules.update', $formule) }}" class="grid items-end gap-3 px-5 py-4 sm:grid-cols-12 {{ $formule->actif ? '' : 'bg-slate-50' }}">
                        @csrf @method('PUT')
                        <div class="sm:col-span-4">
                            <label class="label text-xs">Nom</label>
                            <input name="nom" value="{{ $formule->nom }}" required maxlength="100" class="input">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="label text-xs">Durée (jours)</label>
                            <input type="number" name="duree_jours" value="{{ $formule->duree_jours }}" min="1" max="730" required class="input">
                        </div>
                        <div class="sm:col-span-3">
                            <label class="label text-xs">Prix (FCFA)</label>
                            <input type="number" name="prix" value="{{ $formule->prix }}" min="0" max="10000000" step="500" required class="input">
                        </div>
                        <label class="flex items-center gap-2 pb-2.5 text-sm sm:col-span-1">
                            <input type="checkbox" name="actif" value="1" @checked($formule->actif) class="size-4 accent-brand-500"> Active
                        </label>
                        <div class="sm:col-span-2">
                            <button type="submit" class="btn-light w-full">Enregistrer</button>
                        </div>
                        <p class="flex items-center justify-between gap-3 text-xs text-slate-500 sm:col-span-12"><span>{{ $formule->abonnes_en_cours }} abonnement(s) en cours sur cette formule{{ $formule->actif ? '' : ' · formule masquée à la caisse' }}</span>
                            <button type="submit" form="supprimer-formule-{{ $formule->id }}" class="font-semibold text-red-600 hover:underline">Supprimer</button></p>
                    </form>
                    <form id="supprimer-formule-{{ $formule->id }}" method="POST" action="{{ route('admin.formules.destroy', $formule) }}" data-confirm="Supprimer la formule « {{ $formule->nom }} » ? (Refusé si elle a déjà été vendue.)" hidden>@csrf @method('DELETE')</form>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-400">Aucune formule.</p>
                @endforelse
            </div>
        </div>

        <form method="POST" action="{{ route('admin.formules.store') }}" class="card">
            @csrf
            <div class="card-header"><h2 class="card-title">Nouvelle formule</h2></div>
            <div class="card-body grid items-end gap-3 sm:grid-cols-12">
                <div class="sm:col-span-5"><label class="label text-xs" for="n-nom">Nom</label><input id="n-nom" name="nom" required maxlength="100" class="input" placeholder="ex. Premium mensuel"></div>
                <div class="sm:col-span-2"><label class="label text-xs" for="n-duree">Durée (jours)</label><input id="n-duree" type="number" name="duree_jours" min="1" max="730" required class="input" placeholder="30"></div>
                <div class="sm:col-span-3"><label class="label text-xs" for="n-prix">Prix (FCFA)</label><input id="n-prix" type="number" name="prix" min="0" max="10000000" step="500" required class="input" placeholder="15000"></div>
                <div class="sm:col-span-2"><button type="submit" class="btn-primary w-full"><x-icon name="plus" class="size-4"/> Ajouter</button></div>
            </div>
        </form>
    </div>

    <form method="POST" action="{{ route('admin.formules.tarif') }}" class="card h-fit">
        @csrf @method('PUT')
        <div class="card-header"><h2 class="card-title flex items-center gap-2"><x-icon name="bolt" class="size-5 text-brand-500"/> Passage journalier</h2></div>
        <div class="card-body space-y-4">
            <p class="text-sm text-slate-600">Prix d'une séance à l'unité. La caissière ne peut pas le modifier : il est appliqué automatiquement.</p>
            <div>
                <label for="tarif_journalier" class="label">Tarif (FCFA)</label>
                <input id="tarif_journalier" type="number" name="tarif_journalier" value="{{ $tarifJournalier }}" min="0" max="1000000" step="100" required class="input text-lg font-bold">
            </div>
            <button type="submit" class="btn-primary w-full">Mettre à jour</button>
            <p class="text-center text-xs text-slate-500">Actuel : {{ Fcfa::format($tarifJournalier) }}</p>
        </div>
    </form>
</div>
@endsection
