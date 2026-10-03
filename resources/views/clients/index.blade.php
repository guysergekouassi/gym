@extends('layouts.app')
@section('title', 'Clients')

@php
    use App\Models\Client;
    use Illuminate\Support\Carbon;
    $onglet = fn (?string $type) => request('type') === $type
        ? 'bg-brand-500 text-white shadow-sm'
        : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:text-slate-900';
    $lienType = fn (?string $type) => route('clients.index', array_filter(['type' => $type, 'q' => request('q'), 'statut' => request('statut')]));
    $ouvrirModale = request()->boolean('nouveau') || ($errors->any() && old('_modale') === 'nouveau');
@endphp

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-3xl font-bold tracking-tight text-slate-900">Clients</h1>
        <p class="mt-1 text-slate-500">Fiches des membres, abonnements et dernières visites.</p>
    </div>
    <button type="button" data-dialog-open="dialog-client" class="btn-primary"><x-icon name="plus" class="size-4"/> Nouveau client</button>
</div>

<div class="card mb-6 flex flex-wrap items-center gap-3 p-3">
    <div class="flex flex-wrap gap-2">
        <a href="{{ $lienType(null) }}" class="rounded-xl px-4 py-2 text-sm font-semibold {{ $onglet(null) }}">Tous ({{ $compteurs['tous'] }})</a>
        <a href="{{ $lienType(Client::TYPE_ABONNE) }}" class="rounded-xl px-4 py-2 text-sm font-semibold {{ $onglet(Client::TYPE_ABONNE) }}">Abonnés ({{ $compteurs[Client::TYPE_ABONNE] }})</a>
        <a href="{{ $lienType(Client::TYPE_JOURNALIER) }}" class="rounded-xl px-4 py-2 text-sm font-semibold {{ $onglet(Client::TYPE_JOURNALIER) }}">Passage ({{ $compteurs[Client::TYPE_JOURNALIER] }})</a>
    </div>
    <form method="GET" class="ml-auto flex flex-wrap items-center gap-2">
        @if(request('type'))<input type="hidden" name="type" value="{{ request('type') }}">@endif
        <div class="relative">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"/>
            <input type="search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Nom, téléphone ou n° empreinte" class="input w-64 py-2 pl-9">
        </div>
        <select name="statut" class="input w-auto py-2" aria-label="Statut">
            <option value="">Statut : tous</option>
            <option value="en_regle" @selected(request('statut') === 'en_regle')>En règle</option>
            <option value="expire_bientot" @selected(request('statut') === 'expire_bientot')>Expire bientôt</option>
            <option value="expire" @selected(request('statut') === 'expire')>Expiré</option>
            <option value="a_relancer" @selected(request('statut') === 'a_relancer')>À relancer (absents {{ config('salle.kpi.inactif_jours') }} j)</option>
        </select>
        <button type="submit" class="btn-dark py-2">Filtrer</button>
    </form>
</div>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
            <tr><th>Client</th><th>Type</th><th>Téléphone</th><th>Formule</th><th>Expiration</th><th>Dernière visite</th><th>Statut</th></tr>
            </thead>
            <tbody>
            @forelse($clients as $client)
                @php
                    $fin = $client->fin_droits ? Carbon::parse($client->fin_droits) : null;
                    $enRegle = $fin && $fin->gte(today());
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('clients.show', $client) }}" class="flex items-center gap-3">
                            <x-avatar :client="$client" size="size-9" text="text-xs"/>
                            <span>
                                <span class="block font-semibold text-slate-900">{{ $client->nom_complet }}</span>
                                @if($client->empreinte_id)<span class="inline-flex items-center gap-1 text-xs text-slate-500"><x-icon name="fingerprint" class="size-3"/> n° {{ $client->empreinte_id }}</span>@endif
                            </span>
                        </a>
                    </td>
                    <td>{{ $client->type === Client::TYPE_ABONNE ? 'Abonné' : 'Passage' }}</td>
                    <td class="text-slate-600">{{ $client->telephone ?? '—' }}</td>
                    <td>{{ $client->formule_actuelle ?? '—' }}</td>
                    <td class="text-slate-600">{{ $fin?->format('d/m/Y') ?? '—' }}</td>
                    <td class="text-slate-600">{{ $client->dernier_passage_le ? Carbon::parse($client->dernier_passage_le)->format('d/m/Y') : 'Jamais' }}</td>
                    <td>
                        @if($enRegle)<span class="pill-green"><x-icon name="check" class="size-3"/> Actif</span>
                        @elseif($client->type === Client::TYPE_ABONNE)<span class="pill-red"><x-icon name="x" class="size-3"/> Expiré</span>
                        @else<span class="pill-blue">Passage</span>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-12 text-center text-slate-400">Aucun client ne correspond.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4">{{ $clients->links() }}</div>

{{-- Fenêtre « Nouveau client » --}}
<dialog id="dialog-client" @if($ouvrirModale) data-ouvrir @endif
        class="m-auto w-full max-w-3xl rounded-2xl p-0 shadow-2xl backdrop:bg-ink-950/60 backdrop:backdrop-blur-sm">
    <form method="POST" action="{{ route('clients.store') }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="_modale" value="nouveau">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Nouveau client</h2>
            <button type="button" data-dialog-close class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100" aria-label="Fermer"><x-icon name="x" class="size-5"/></button>
        </div>

        @if($errors->any() && old('_modale') === 'nouveau')
            <div class="mx-6 mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-600/20">
                @foreach($errors->all() as $erreur)<p>{{ $erreur }}</p>@endforeach
            </div>
        @endif

        <div class="grid gap-6 p-6 md:grid-cols-2">
            <div class="space-y-4">
                <p class="text-sm font-semibold text-slate-900">Informations personnelles</p>
                <div><label class="label" for="m-nom">Nom *</label><input id="m-nom" name="nom" required maxlength="100" value="{{ old('nom') }}" class="input" placeholder="Nom du client"></div>
                <div><label class="label" for="m-prenoms">Prénoms</label><input id="m-prenoms" name="prenoms" maxlength="150" value="{{ old('prenoms') }}" class="input" placeholder="Prénoms du client"></div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="m-type">Type *</label>
                        <select id="m-type" name="type" required class="input">
                            <option value="{{ Client::TYPE_ABONNE }}" @selected(old('type', Client::TYPE_ABONNE) === Client::TYPE_ABONNE)>Abonné</option>
                            <option value="{{ Client::TYPE_JOURNALIER }}" @selected(old('type') === Client::TYPE_JOURNALIER)>Passage</option>
                        </select>
                    </div>
                    <div><label class="label" for="m-tel">Téléphone</label><input id="m-tel" type="tel" name="telephone" maxlength="20" value="{{ old('telephone') }}" class="input" placeholder="07 12 34 56 78"></div>
                </div>
                <div><label class="label" for="m-email">E-mail</label><input id="m-email" type="email" name="email" maxlength="150" value="{{ old('email') }}" class="input" placeholder="email@exemple.com"></div>
                <div><label class="label" for="m-notes">Notes</label><textarea id="m-notes" name="notes" rows="2" maxlength="1000" class="input" placeholder="Informations complémentaires…">{{ old('notes') }}</textarea></div>
            </div>
            <div class="space-y-4">
                <p class="text-sm font-semibold text-slate-900">Informations complémentaires</p>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label" for="m-naissance">Date de naissance</label><input id="m-naissance" type="date" name="date_naissance" value="{{ old('date_naissance') }}" class="input"></div>
                    <div>
                        <label class="label" for="m-sexe">Sexe</label>
                        <select id="m-sexe" name="sexe" class="input"><option value="">—</option><option value="M" @selected(old('sexe') === 'M')>Homme</option><option value="F" @selected(old('sexe') === 'F')>Femme</option></select>
                    </div>
                </div>
                <div>
                    <label class="label" for="m-empreinte">N° empreinte (pointeuse)</label>
                    <div class="flex gap-2">
                        <input id="m-empreinte" name="empreinte_id" maxlength="9" inputmode="numeric" value="{{ old('empreinte_id') }}" class="input font-mono" placeholder="ex. {{ $numeroSuggere }}">
                        <button type="button" class="btn-light shrink-0" data-remplir-garder="m-empreinte" data-valeur="{{ $numeroSuggere }}">N° {{ $numeroSuggere }}</button>
                    </div>
                    <p class="hint">Enregistrez ensuite le doigt sur la pointeuse avec ce même n°.</p>
                </div>
                <div>
                    <span class="label">Photo (optionnel)</span>
                    <label class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-200 px-4 py-5 text-center text-sm text-slate-500 hover:border-brand-300 hover:bg-brand-50/40">
                        <x-icon name="user-plus" class="mb-1 size-6"/> Télécharger une photo
                        <span class="text-xs">JPG, PNG ou WebP · 2 Mo max</span>
                        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="mt-2 max-w-full text-xs">
                    </label>
                </div>
                <label class="flex items-center gap-2 rounded-xl bg-brand-50 px-3 py-2.5 text-sm text-brand-800">
                    <input type="checkbox" name="abonner" value="1" @checked(old('abonner', request('nouveau') ? true : false)) class="size-4 accent-brand-500">
                    Ouvrir la caisse pour l'abonner juste après
                </label>
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-6 py-4">
            <button type="button" data-dialog-close class="btn-light">Annuler</button>
            <button type="submit" class="btn-primary"><x-icon name="check" class="size-4"/> Enregistrer</button>
        </div>
    </form>
</dialog>
@endsection
