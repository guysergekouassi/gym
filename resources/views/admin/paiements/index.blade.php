@extends('layouts.app')
@section('title', 'Encaissements')

@php
    use App\Models\Paiement;
    use App\Support\Fcfa;
@endphp

@section('content')
<x-page-header title="Encaissements" subtitle="Contrôle de caisse : tous les tickets émis, par période, caissière et mode de paiement.">
    <a href="{{ route('admin.paiements.export', request()->query()) }}" class="btn-light"><x-icon name="download" class="size-4"/> Export Excel (CSV)</a>
</x-page-header>

<x-filtre-periode :periode="$filtres['periode']" :annees="$annees" class="mb-4"
    :conserver="['user_id' => $filtres['user_id'], 'mode' => $filtres['mode'], 'type' => $filtres['type']]"/>

<form method="GET" class="card mb-6 flex flex-wrap items-end gap-3 p-4">
    @foreach($filtres['periode']->parametres() as $nom => $valeur)<input type="hidden" name="{{ $nom }}" value="{{ $valeur }}">@endforeach
    <div>
        <label class="label text-xs" for="user_id">Caissière</label>
        <select id="user_id" name="user_id" class="input">
            <option value="">Toutes</option>
            @foreach($caissiers as $c)<option value="{{ $c->id }}" @selected($filtres['user_id'] === $c->id)>{{ $c->name }}</option>@endforeach
        </select>
    </div>
    <div>
        <label class="label text-xs" for="mode">Mode</label>
        <select id="mode" name="mode" class="input">
            <option value="">Tous</option>
            @foreach(Paiement::MODES as $v => $l)<option value="{{ $v }}" @selected($filtres['mode'] === $v)>{{ $l }}</option>@endforeach
        </select>
    </div>
    <div>
        <label class="label text-xs" for="type">Objet</label>
        <select id="type" name="type" class="input">
            <option value="">Tous</option>
            @foreach(Paiement::TYPES as $v => $l)<option value="{{ $v }}" @selected($filtres['type'] === $v)>{{ $l }}</option>@endforeach
        </select>
    </div>
    <button type="submit" class="btn-dark">Filtrer</button>
</form>

<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat label="Total encaissé" :value="Fcfa::format($total)" icon="cash" tone="brand" :hint="$nombre.' ticket(s) valide(s)'"/>
    <x-stat label="Tickets annulés" :value="$annules" icon="x" tone="red" hint="Exclus du total"/>
    <div class="card p-5 sm:col-span-2">
        <p class="text-sm font-medium text-slate-500">Répartition</p>
        <div class="mt-3 grid gap-x-6 gap-y-1.5 text-sm sm:grid-cols-2">
            @forelse($parMode as $ligne)
                <div class="flex justify-between"><span class="text-slate-600">{{ Paiement::MODES[$ligne->mode] ?? $ligne->mode }} <span class="text-slate-400">({{ $ligne->nombre }})</span></span><span class="font-semibold">{{ Fcfa::format($ligne->total) }}</span></div>
            @empty
                <p class="text-slate-400">—</p>
            @endforelse
            @foreach($parCaissier as $ligne)
                <div class="flex justify-between"><span class="text-slate-600">{{ $ligne->user?->name ?? '—' }} <span class="text-slate-400">({{ $ligne->nombre }})</span></span><span class="font-semibold">{{ Fcfa::format($ligne->total) }}</span></div>
            @endforeach
        </div>
    </div>
</div>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="table">
            <thead><tr><th>Ticket</th><th>Date</th><th>Client</th><th>Objet</th><th>Mode</th><th>Caissière</th><th class="text-right">Montant</th><th></th></tr></thead>
            <tbody>
            @forelse($paiements as $p)
                <tr class="{{ $p->estAnnule() ? 'bg-red-50/40' : '' }}">
                    <td><a href="{{ route('recus.show', $p) }}" class="link whitespace-nowrap font-mono text-xs">{{ $p->numero_recu }}</a></td>
                    <td class="whitespace-nowrap text-slate-600">{{ $p->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $p->client?->nom_complet ?? 'Anonyme' }}</td>
                    <td>{{ $p->abonnement ? 'Abonnement '.$p->abonnement->formule->nom : 'Passage'.($p->quantite > 1 ? ' × '.$p->quantite : '') }}</td>
                    <td>{{ Paiement::MODES[$p->mode] ?? $p->mode }}@if($p->reference)<span class="block font-mono text-xs text-slate-400">{{ $p->reference }}</span>@endif</td>
                    <td>{{ $p->user?->name ?? '—' }}</td>
                    <td class="whitespace-nowrap text-right font-semibold {{ $p->estAnnule() ? 'text-slate-400 line-through' : '' }}">{{ Fcfa::format($p->montant) }}</td>
                    <td class="text-right">
                        @if($p->estAnnule())
                            <span class="pill-red" title="{{ $p->motif_annulation }}">Annulé</span>
                            <span class="mt-1 block text-[11px] text-slate-500">par {{ $p->annulePar?->name ?? '—' }} le {{ $p->annule_le->format('d/m H:i') }}</span>
                        @else
                            <button type="button" class="btn-danger btn-sm" data-dialog-open="dialog-annuler"
                                    data-action="{{ route('admin.paiements.annuler', $p) }}"
                                    data-label="{{ $p->numero_recu }} · {{ Fcfa::format($p->montant) }} · {{ $p->client?->nom_complet ?? 'Anonyme' }}">Annuler</button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-12 text-center text-slate-400">Aucun encaissement sur cette période.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4">{{ $paiements->links() }}</div>

<dialog id="dialog-annuler" class="m-auto w-full max-w-md rounded-2xl p-0 shadow-2xl backdrop:bg-ink-950/60 backdrop:backdrop-blur-sm">
    <form method="POST" action="#" class="p-6">
        @csrf
        <div class="flex items-start gap-3">
            <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600"><x-icon name="alert"/></span>
            <div>
                <h3 class="text-lg font-semibold text-slate-900">Annuler ce ticket ?</h3>
                <p data-dialog-label class="mt-1 text-sm text-slate-600"></p>
                <p class="mt-2 text-xs text-slate-500">Le ticket reste visible (barré) avec votre nom et le motif. S'il s'agit d'un abonnement, celui-ci est désactivé.</p>
            </div>
        </div>
        <label for="motif" class="label mt-5">Motif *</label>
        <input id="motif" name="motif" required minlength="5" maxlength="255" class="input" placeholder="ex. erreur de formule, client remboursé…">
        <div class="mt-6 flex justify-end gap-2">
            <button type="button" data-dialog-close class="btn-light">Retour</button>
            <button type="submit" class="btn bg-red-600 text-white hover:bg-red-700">Confirmer l'annulation</button>
        </div>
    </form>
</dialog>
@endsection
