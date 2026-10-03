@extends('layouts.app')
@section('title', 'Pointeuses')

@section('content')
<div class="mb-6">
    <h1 class="text-3xl font-bold tracking-tight text-slate-900">Pointeuses</h1>
    <p class="mt-1 text-slate-500">Pointeuse Hikvision reliée à l'application : les passages arrivent en direct, les membres et leurs dates d'abonnement partent automatiquement.</p>
</div>

<div class="grid gap-6 xl:grid-cols-3">
    <div class="space-y-6 xl:col-span-2">
        <div class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Pointeuses</h2></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Pointeuse</th><th>Liaison</th><th>Adresse</th><th>Envois</th><th></th></tr></thead>
                    <tbody>
                    @forelse($pointeuses as $p)
                        <tr class="{{ $p->actif ? '' : 'opacity-60' }}">
                            <td>
                                <span class="block font-semibold">{{ $p->nom }}</span>
                                <span class="text-xs text-slate-500">{{ $p->modele ?? 'Modèle inconnu (cliquez sur Tester)' }}@if($p->numero_serie) · {{ $p->numero_serie }}@endif</span>
                            </td>
                            <td>
                                @if(! $p->actif)<span class="pill-gray">Désactivée</span>
                                @elseif($p->derniere_erreur)<span class="pill-red"><x-icon name="x" class="size-3"/> Erreur</span>
                                @elseif($p->estEnLigne())<span class="pill-green"><x-icon name="check" class="size-3"/> En ligne</span>
                                @else<span class="pill-amber">En attente</span>@endif
                                <span class="mt-1 block max-w-64 text-xs text-slate-500">{{ $p->derniere_erreur ?? ($p->derniere_activite_at ? 'Dernier contact '.$p->derniere_activite_at->diffForHumans() : 'Jamais contactée') }}</span>
                            </td>
                            <td class="font-mono text-xs">{{ $p->adresse_ip }}:{{ $p->port }}</td>
                            <td class="text-xs">
                                @if($p->en_attente)<span class="pill-amber">{{ $p->en_attente }} en attente</span>@endif
                                @if($p->en_erreur)<span class="pill-red">{{ $p->en_erreur }} en erreur</span>@endif
                                @if(! $p->en_attente && ! $p->en_erreur)<span class="text-slate-400">À jour</span>@endif
                            </td>
                            <td>
                                @if($p->actif)
                                    <div class="flex flex-wrap justify-end gap-1.5">
                                        <form method="POST" action="{{ route('admin.pointeuses.tester', $p) }}">@csrf<button type="submit" class="btn-primary btn-sm">Tester</button></form>
                                        <form method="POST" action="{{ route('admin.pointeuses.synchroniser', $p) }}">@csrf<button type="submit" class="btn-light btn-sm">Envoyer les membres</button></form>
                                        <a href="{{ route('admin.pointeuses.index', ['modifier' => $p->id]) }}" class="btn-light btn-sm">Modifier</a>
                                        <form method="POST" action="{{ route('admin.pointeuses.destroy', $p) }}" data-confirm="Désactiver « {{ $p->nom }} » ?">@csrf @method('DELETE')<button type="submit" class="btn-danger btn-sm">Désactiver</button></form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-10 text-center text-slate-400">Aucune pointeuse. Renseignez son adresse IP et son mot de passe dans le formulaire.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card card-body text-sm text-slate-700">
            <p class="mb-3 font-semibold text-slate-900">Pour que les passages arrivent</p>
            <ol class="list-decimal space-y-1.5 pl-5">
                <li>La pointeuse et ce PC sont reliés (câble réseau direct, box ou Wi-Fi) : voir le guide d'installation.</li>
                <li>Renseignez l'adresse IP de la pointeuse et le mot de passe choisi à son activation, puis <b>Tester</b>.</li>
                <li>Le programme d'écoute doit tourner : il est lancé par <code>demarrer-gymflow.bat</code> (fenêtre « Pointeuse »).</li>
                <li>Cliquez sur « Envoyer les membres », puis enregistrez le doigt de chaque membre sur la pointeuse.</li>
            </ol>
            <p class="mt-3 text-xs text-slate-500">La pointeuse refuse d'elle-même un membre dont l'abonnement est terminé : GymFlow lui envoie la date de fin à chaque paiement.</p>
        </div>
    </div>

    @php $p = $modifiee; @endphp
    <form method="POST" action="{{ $p ? route('admin.pointeuses.update', $p) : route('admin.pointeuses.store') }}" class="card h-fit">
        @csrf
        @if($p) @method('PUT') @endif
        <div class="card-header"><h2 class="card-title">{{ $p ? 'Modifier « '.$p->nom.' »' : 'Ajouter la pointeuse' }}</h2></div>
        <div class="card-body space-y-4">
            <div><label class="label" for="p-nom">Nom</label><input id="p-nom" name="nom" required maxlength="100" value="{{ old('nom', $p?->nom ?? 'Entrée principale') }}" class="input"></div>
            <div class="grid grid-cols-3 gap-3">
                <div class="col-span-2">
                    <label class="label" for="p-ip">Adresse IP</label>
                    <input id="p-ip" name="adresse_ip" required maxlength="15" inputmode="decimal" value="{{ old('adresse_ip', $p?->adresse_ip) }}" class="input font-mono" placeholder="192.168.50.64">
                </div>
                <div><label class="label" for="p-port">Port</label><input id="p-port" name="port" type="number" min="1" max="65535" required value="{{ old('port', $p?->port ?? 80) }}" class="input font-mono"></div>
            </div>
            <div><label class="label" for="p-id">Identifiant</label><input id="p-id" name="identifiant" required maxlength="32" value="{{ old('identifiant', $p?->identifiant ?? 'admin') }}" class="input font-mono" autocomplete="off"></div>
            <div>
                <label class="label" for="p-mdp">Mot de passe de la pointeuse</label>
                <input id="p-mdp" name="mot_de_passe" type="password" maxlength="64" @required(! $p) class="input" autocomplete="new-password" placeholder="{{ $p ? 'Laisser vide pour ne pas changer' : 'Choisi à l\'activation' }}">
                <p class="hint">Stocké chiffré, jamais réaffiché.</p>
            </div>
            <button type="submit" class="btn-primary w-full">{{ $p ? 'Enregistrer' : 'Ajouter' }}</button>
            @if($p)<a href="{{ route('admin.pointeuses.index') }}" class="btn-light w-full">Annuler</a>@endif
        </div>
    </form>
</div>
@endsection
