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
            @if(auth()->user()->isAdmin())
                <option value="masques" @selected(request('statut') === 'masques')>Masqués ({{ $nombreMasques }})</option>
            @endif
        </select>
        <button type="submit" class="btn-dark py-2">Filtrer</button>
    </form>
</div>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
            <tr><th>Client</th><th>Type</th><th>Téléphone</th><th>Formule</th><th>Expiration</th><th>Dernière visite</th><th>Statut</th>@if(auth()->user()->isAdmin())<th class="text-right">Actions</th>@endif</tr>
            </thead>
            <tbody>
            @forelse($clients as $client)
                @php
                    $fin = $client->fin_droits ? Carbon::parse($client->fin_droits) : null;
                    $enRegle = $fin && $fin->gte(today());
                @endphp
                <tr>
                    <td>
                        <a @unless($masques) href="{{ route('clients.show', $client) }}" @endunless class="flex items-center gap-3">
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
                        @if($masques)<span class="pill-gray">Masqué</span>
                        @elseif($enRegle)<span class="pill-green"><x-icon name="check" class="size-3"/> Actif</span>
                        @elseif($client->type === Client::TYPE_ABONNE)<span class="pill-red"><x-icon name="x" class="size-3"/> Expiré</span>
                        @else<span class="pill-blue">Passage</span>@endif
                    </td>
                    @if(auth()->user()->isAdmin())
                        <td>
                            <div class="flex justify-end gap-1.5">
                                @if($masques)
                                    <form method="POST" action="{{ route('clients.restaurer', $client->id) }}">@csrf
                                        <button type="submit" class="btn-light btn-sm"><x-icon name="refresh" class="size-3.5"/> Réafficher</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('clients.destroy', $client) }}" data-confirm="Masquer {{ $client->nom_complet }} ? Il disparaît de la liste et de la pointeuse, mais reste dans l'historique de caisse.">@csrf @method('DELETE')
                                        <button type="submit" class="btn-light btn-sm" title="Masquer (réversible)"><x-icon name="archive" class="size-3.5"/> Masquer</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('clients.supprimer', $client->id) }}" data-confirm="Supprimer DÉFINITIVEMENT {{ $client->nom_complet }} ? Impossible à annuler. (Refusé s'il a des paiements.)">@csrf @method('DELETE')
                                    <button type="submit" class="btn-danger btn-sm" title="Supprimer définitivement" aria-label="Supprimer définitivement"><x-icon name="x" class="size-3.5"/></button>
                                </form>
                            </div>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="8" class="py-12 text-center text-slate-400">Aucun client ne correspond.</td></tr>
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
                    <div><label class="label" for="m-adhesion">Date d'adhésion</label><input id="m-adhesion" type="date" name="date_adhesion" value="{{ old('date_adhesion', today()->toDateString()) }}" max="{{ today()->addYear()->toDateString() }}" class="input"></div>
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
                <x-champ-photo id="m-photo"/>

                {{-- Abonnement immédiat (visible seulement pour type Abonné) --}}
                <div id="m-bloc-abonnement" style="display: {{ old('type', 'abonne') === Client::TYPE_ABONNE ? 'block' : 'none' }}">
                    <p class="text-sm font-semibold text-slate-900 mb-2">Abonnement immédiat <span class="font-normal opacity-50">(optionnel)</span></p>
                    <div class="space-y-3">
                        <div>
                            <label class="label" for="m-formule">Formule</label>
                            <select id="m-formule" name="formule_id" class="input">
                                <option value="">— Choisir une formule —</option>
                                @foreach($formules as $formule)
                                    <option value="{{ $formule->id }}"
                                            data-prix="{{ number_format($formule->prix, 0, ',', ' ') }}"
                                            data-resume="{{ $formule->resume() }}"
                                            @selected(old('formule_id') == $formule->id)>
                                        {{ $formule->nom }} — {{ number_format($formule->prix, 0, ',', ' ') }} FCFA
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div id="m-bloc-paiement" style="display: {{ old('formule_id') ? 'block' : 'none' }}" class="space-y-3">
                            <div>
                                <label class="label" for="m-reference">Référence <span class="opacity-50">(optionnel)</span></label>
                                <input id="m-reference" type="text" name="reference" maxlength="100"
                                       value="{{ old('reference') }}" class="input" placeholder="ex. Wave #123456">
                            </div>
                            <div id="m-apercu" class="rounded-lg bg-brand-50 border border-brand-100 px-4 py-3 text-sm hidden">
                                <div class="font-semibold text-brand-800" id="m-apercu-nom"></div>
                                <div class="text-brand-600 text-xs mt-0.5" id="m-apercu-detail"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-6 py-4">
            <button type="button" data-dialog-close class="btn-light">Annuler</button>
            <button type="submit" class="btn-primary"><x-icon name="check" class="size-4"/> Enregistrer</button>
        </div>
    </form>
</dialog>

@push('scripts')
<script>
(function () {
    const mType       = document.getElementById('m-type');
    const mBloc       = document.getElementById('m-bloc-abonnement');
    const mFormule    = document.getElementById('m-formule');
    const mBlocPmt    = document.getElementById('m-bloc-paiement');
    const mApercu     = document.getElementById('m-apercu');
    const mApercuNom  = document.getElementById('m-apercu-nom');
    const mApercuDet  = document.getElementById('m-apercu-detail');

    if (mType) {
        mType.addEventListener('change', () => {
            const estAbonne = mType.value === '{{ Client::TYPE_ABONNE }}';
            mBloc.style.display = estAbonne ? 'block' : 'none';
            if (!estAbonne) { mFormule.value = ''; mBlocPmt.style.display = 'none'; }
        });
    }

    if (mFormule) {
        mFormule.addEventListener('change', () => {
            const opt = mFormule.selectedOptions[0];
            if (opt && opt.value) {
                mBlocPmt.style.display = 'block';
                mApercuNom.textContent = opt.text.split('—')[0].trim();
                mApercuDet.textContent = opt.dataset.resume + ' · ' + opt.dataset.prix + ' FCFA';
                mApercu.classList.remove('hidden');
            } else {
                mBlocPmt.style.display = 'none';
                mApercu.classList.add('hidden');
            }
        });
        if (mFormule.value) mFormule.dispatchEvent(new Event('change'));
    }
})();
</script>
@endpush
@endsection
