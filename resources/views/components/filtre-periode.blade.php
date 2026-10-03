@props(['periode', 'annees', 'conserver' => []])
@php
    use App\Support\Periode;
    $calendrier = $periode->mode === 'calendrier';
    $semaines = $periode->mois ? Periode::semainesDuMois($periode->annee, $periode->mois) : [];
    $bouton = 'rounded-lg px-4 py-2 text-sm font-semibold transition';
    $actifBouton = 'bg-ink-900 text-white shadow-sm';
    $inactifBouton = 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50';
    $champ = 'rounded-lg border-0 bg-amber-50/70 py-2 pl-3 pr-8 text-sm text-slate-800 ring-1 ring-amber-200/70 focus:ring-2 focus:ring-brand-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 disabled:ring-slate-200';
@endphp
<form method="GET" data-filtre-periode {{ $attributes->merge(['class' => 'card flex flex-col gap-3 p-4']) }}>
    @foreach($conserver as $nom => $valeur)
        @if($valeur !== null && $valeur !== '')<input type="hidden" name="{{ $nom }}" value="{{ $valeur }}">@endif
    @endforeach
    <input type="hidden" name="mode" value="{{ $periode->mode }}" data-mode-champ>

    <div class="flex flex-wrap items-center gap-2">
        <button type="button" data-mode-bouton="calendrier" class="{{ $bouton }} {{ $calendrier ? $actifBouton : $inactifBouton }}"
                data-classe-actif="{{ $actifBouton }}" data-classe-inactif="{{ $inactifBouton }}">Calendrier</button>
        <button type="button" data-mode-bouton="periode" class="{{ $bouton }} {{ $calendrier ? $inactifBouton : $actifBouton }}"
                data-classe-actif="{{ $actifBouton }}" data-classe-inactif="{{ $inactifBouton }}">Période</button>
        <span class="ml-auto inline-flex items-center gap-1.5 rounded-lg bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-700">
            <x-icon name="calendar" class="size-3.5"/> {{ $periode->libelle() }}
        </span>
    </div>

    {{-- Calendrier : exercice → mois → semaine → jour --}}
    <div data-mode-bloc="calendrier" class="flex flex-wrap items-center gap-2" @unless($calendrier) hidden @endunless>
        <label class="inline-flex items-center gap-1 text-sm font-semibold text-slate-600">
            Exercice
            <select name="annee" data-auto aria-label="Exercice" @disabled(! $calendrier)
                    class="cursor-pointer rounded-lg border-0 bg-transparent py-1 pl-1 pr-7 text-sm font-bold text-slate-900 hover:bg-slate-100 focus:ring-2 focus:ring-brand-500">
                @foreach($annees as $a)<option value="{{ $a }}" @selected($a === $periode->annee)>{{ $a }}</option>@endforeach
            </select>
        </label>
        <select name="mois" data-auto data-reinitialiser="semaine,jour" aria-label="Mois" class="{{ $champ }}" @disabled(! $calendrier)>
            <option value="">Tous les mois</option>
            @foreach(Periode::MOIS as $n => $nom)<option value="{{ $n }}" @selected($n === $periode->mois)>{{ $nom }}</option>@endforeach
        </select>
        <select name="semaine" data-auto data-reinitialiser="jour" aria-label="Semaine" class="{{ $champ }}" @disabled(! $calendrier || ! $periode->mois)
                title="{{ $periode->mois ? '' : 'Choisissez d\'abord un mois' }}">
            <option value="">Toutes les semaines</option>
            @foreach($semaines as $n => $s)<option value="{{ $n }}" @selected($n === $periode->semaine)>{{ $s['libelle'] }}</option>@endforeach
        </select>
        <select name="jour" data-auto aria-label="Jour" class="{{ $champ }}" @disabled(! $calendrier || ! $periode->mois)
                title="{{ $periode->mois ? '' : 'Choisissez d\'abord un mois' }}">
            <option value="">Tous les jours</option>
            @foreach($periode->joursProposes() as $n => $libelle)<option value="{{ $n }}" @selected($n === $periode->jour)>{{ $libelle }}</option>@endforeach
        </select>
    </div>

    {{-- Période précise : du … au --}}
    <div data-mode-bloc="periode" class="flex flex-wrap items-center gap-2" @if($calendrier) hidden @endif>
        <label class="text-sm font-semibold text-slate-600" for="filtre-du">Du</label>
        <input id="filtre-du" type="date" name="du" value="{{ $periode->du->toDateString() }}" required class="{{ $champ }} pr-3" @disabled($calendrier)>
        <label class="text-sm font-semibold text-slate-600" for="filtre-au">Au</label>
        <input id="filtre-au" type="date" name="au" value="{{ $periode->au->toDateString() }}" required class="{{ $champ }} pr-3" @disabled($calendrier)>
        <button type="submit" class="btn-primary btn-sm py-2">Appliquer</button>
    </div>
</form>
