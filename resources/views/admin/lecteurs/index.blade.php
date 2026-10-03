@extends('layouts.app')
@section('title', 'Lecteurs de badge')

@section('content')
<x-page-header title="Lecteurs de badge" subtitle="Deux façons de brancher le lecteur : en USB sur le poste d'accueil (rien à configurer) ou en réseau via l'API (token ci-dessous)."/>

@if(session('token_lecteur'))
    <div class="card mb-6 border-l-4 border-amber-400 p-5">
        <p class="font-semibold text-slate-900">Token du lecteur « {{ session('token_lecteur')['nom'] }} »</p>
        <p class="mt-1 text-sm text-amber-800">Copiez-le maintenant dans la configuration du boîtier : il ne sera plus jamais affiché (seule son empreinte est conservée).</p>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <code id="token-lecteur" class="select-all rounded-lg bg-slate-900 px-3 py-2 font-mono text-sm text-emerald-300">{{ session('token_lecteur')['token'] }}</code>
            <button type="button" data-copier="token-lecteur" class="btn-light btn-sm">Copier</button>
        </div>
    </div>
@endif

<div class="grid gap-6 lg:grid-cols-3">
    <div class="card overflow-hidden lg:col-span-2">
        <div class="card-header"><h2 class="card-title">Lecteurs réseau</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Nom</th><th>Statut</th><th>Dernière activité</th><th></th></tr></thead>
                <tbody>
                @forelse($lecteurs as $lecteur)
                    <tr class="{{ $lecteur->actif ? '' : 'opacity-60' }}">
                        <td class="font-semibold">{{ $lecteur->nom }}</td>
                        <td>@if($lecteur->actif)<span class="pill-green">Actif</span>@else<span class="pill-gray">Révoqué</span>@endif</td>
                        <td class="text-slate-600">{{ $lecteur->derniere_activite_at?->diffForHumans() ?? 'Jamais' }}</td>
                        <td class="text-right">
                            @if($lecteur->actif)
                                <form method="POST" action="{{ route('admin.lecteurs.destroy', $lecteur) }}" data-confirm="Révoquer « {{ $lecteur->nom }} » ? Son token cessera de fonctionner.">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn-danger btn-sm">Révoquer</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-slate-400">Aucun lecteur réseau. Inutile si votre lecteur est branché en USB.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="space-y-6">
        <form method="POST" action="{{ route('admin.lecteurs.store') }}" class="card">
            @csrf
            <div class="card-header"><h2 class="card-title">Ajouter un lecteur réseau</h2></div>
            <div class="card-body space-y-3">
                <input name="nom" required maxlength="100" class="input" placeholder="ex. Entrée principale">
                <button type="submit" class="btn-primary w-full"><x-icon name="key" class="size-4"/> Générer un token</button>
            </div>
        </form>
        <div class="card card-body text-sm text-slate-600">
            <p class="mb-2 font-semibold text-slate-900">Lecteur USB (le plus simple)</p>
            <ol class="list-decimal space-y-1 pl-4">
                <li>Branchez le lecteur sur le PC de l'accueil.</li>
                <li>Ouvrez l'<a href="{{ route('accueil.index') }}" target="_blank" rel="noopener" class="link">écran d'accueil</a> (session caisse ouverte).</li>
                <li>Le client passe son badge : l'écran affiche vert ou rouge.</li>
            </ol>
            <p class="mb-2 mt-4 font-semibold text-slate-900">Lecteur réseau (API)</p>
            <p class="font-mono text-xs">POST {{ url('/api/pointage/badge') }}<br>Authorization: Bearer &lt;token&gt;<br>badge_id=0012345678</p>
        </div>
    </div>
</div>
@endsection
