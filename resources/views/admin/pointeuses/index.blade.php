@extends('layouts.app')
@section('title', 'Pointeuses')

@section('content')
<x-page-header title="Pointeuses" subtitle="Pointeuses à empreinte reliées à l'application : les passages arrivent en temps réel, les membres sont envoyés automatiquement."/>

@if(session('token_lecteur'))
    <div class="card mb-6 border-l-4 border-amber-400 p-5">
        <p class="font-semibold text-slate-900">Token API de « {{ session('token_lecteur')['nom'] }} »</p>
        <p class="mt-1 text-sm text-amber-800">Copiez-le maintenant : il ne sera plus jamais affiché (seule son empreinte SHA-256 est conservée).</p>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <code id="token-lecteur" class="select-all rounded-lg bg-slate-900 px-3 py-2 font-mono text-sm text-emerald-300">{{ session('token_lecteur')['token'] }}</code>
            <button type="button" data-copier="token-lecteur" class="btn-light btn-sm">Copier</button>
        </div>
    </div>
@endif

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <div class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Pointeuses déclarées</h2></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Pointeuse</th><th>Liaison</th><th>Adresse IP</th><th>Envois</th><th></th></tr></thead>
                    <tbody>
                    @forelse($pointeuses as $p)
                        <tr class="{{ $p->actif ? '' : 'opacity-60' }}">
                            <td><span class="block font-semibold">{{ $p->nom }}</span><span class="font-mono text-xs text-slate-500">{{ $p->numero_serie ?? 'API (token)' }}</span></td>
                            <td>
                                @if(! $p->actif)<span class="pill-gray">Désactivée</span>
                                @elseif($p->estEnLigne())<span class="pill-green">En ligne</span>
                                @else<span class="pill-red">Hors ligne</span>@endif
                                <span class="mt-1 block text-xs text-slate-500">{{ $p->derniere_activite_at ? 'Vue '.$p->derniere_activite_at->diffForHumans() : 'Jamais connectée' }}</span>
                            </td>
                            <td class="font-mono text-xs">{{ $p->adresse_ip ?? '—' }}</td>
                            <td class="text-xs">
                                @if($p->en_attente)<span class="pill-amber">{{ $p->en_attente }} en attente</span>@endif
                                @if($p->en_erreur)<span class="pill-red">{{ $p->en_erreur }} en erreur</span>@endif
                                @if(! $p->en_attente && ! $p->en_erreur)<span class="text-slate-400">À jour</span>@endif
                            </td>
                            <td>
                                @if($p->actif)
                                    <div class="flex flex-wrap justify-end gap-1.5">
                                        @if($p->numero_serie)
                                            <form method="POST" action="{{ route('admin.pointeuses.synchroniser', $p) }}">@csrf<button type="submit" class="btn-light btn-sm">Envoyer tous les membres</button></form>
                                            <form method="POST" action="{{ route('admin.pointeuses.ip', $p) }}" data-confirm="Réinitialiser l'adresse IP de « {{ $p->nom }} » ? Elle sera réapprise à sa prochaine connexion.">@csrf<button type="submit" class="btn-light btn-sm">Nouvelle IP</button></form>
                                        @endif
                                        <form method="POST" action="{{ route('admin.pointeuses.destroy', $p) }}" data-confirm="Désactiver « {{ $p->nom }} » ?">@csrf @method('DELETE')<button type="submit" class="btn-danger btn-sm">Désactiver</button></form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-10 text-center text-slate-400">Aucune pointeuse. Déclarez-la avec son n° de série (étiquette au dos).</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card card-body text-sm text-slate-700">
            <p class="mb-3 font-semibold text-slate-900">Réglage à faire sur la pointeuse</p>
            <ol class="list-decimal space-y-1.5 pl-5">
                <li>Menu (<b>M/OK</b>) → <b>Comm.</b> → <b>Ethernet</b> : IP fixe, par ex. <code>192.168.1.201</code>, même passerelle que le PC.</li>
                <li>Menu → <b>Comm.</b> → <b>Paramètre serveur Cloud</b> (ou ADMS) :
                    adresse du serveur <code class="font-bold">{{ $adresseServeur }}</code>, port <code class="font-bold">{{ $portServeur }}</code>, nom de domaine : <b>Non</b>, proxy : <b>Non</b>.</li>
                <li>Redémarrer la pointeuse. Ici, l'état passe à <span class="pill-green">En ligne</span> en moins d'une minute.</li>
                <li>Cliquez « Envoyer tous les membres », puis enregistrez le doigt de chaque membre sur la pointeuse (Menu → Utilisateurs → son n° → Empreinte).</li>
            </ol>
            <p class="mt-3 text-xs text-slate-500">L'adresse ci-dessus doit être l'IP locale du PC qui fait tourner l'application (ex. 192.168.1.10), pas « localhost ».</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.pointeuses.store') }}" class="card h-fit">
        @csrf
        <div class="card-header"><h2 class="card-title">Déclarer une pointeuse</h2></div>
        <div class="card-body space-y-4">
            <div><label class="label" for="p-nom">Nom</label><input id="p-nom" name="nom" required maxlength="100" class="input" placeholder="ex. Entrée principale"></div>
            <div>
                <label class="label" for="p-sn">N° de série</label>
                <input id="p-sn" name="numero_serie" maxlength="50" class="input font-mono" placeholder="ex. CQZ7224560123">
                <p class="hint">Sur l'étiquette au dos, ou Menu → Infos système → Infos appareil. Laissez vide pour un accès API par token (avancé).</p>
            </div>
            <button type="submit" class="btn-primary w-full">Déclarer</button>
        </div>
    </form>
</div>
@endsection
