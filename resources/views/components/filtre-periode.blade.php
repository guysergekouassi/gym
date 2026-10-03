@props(['periode', 'annees', 'conserver' => []])
@php
    use App\Support\Periode;
    $calendrier = $periode->mode === 'calendrier';
    $semaines = $periode->mois ? Periode::semainesDuMois($periode->annee, $periode->mois) : [];
    $bouton = 'flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition';
    $actifBouton = 'bg-white text-slate-900 shadow-sm';
    $inactifBouton = 'text-slate-500 hover:text-slate-800';
    $champ = 'input py-2 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400';
@endphp
{{-- Sélecteur de période : bouton compact dans l'en-tête, panneau Calendrier / Période --}}
<details data-filtre-panneau {{ $attributes->merge(['class' => 'group relative']) }}>
    <summary class="flex cursor-pointer list-none items-center gap-3 rounded-xl bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 hover:ring-brand-300 [&::-webkit-details-marker]:hidden">
        <x-icon name="calendar" class="size-5 text-slate-500"/>
        <span class="first-letter:uppercase">{{ $periode->libelle() }}</span>
        <x-icon name="chevron-down" class="size-4 text-slate-500 transition group-open:rotate-180"/>
    </summary>

    <form method="GET" data-filtre-periode
          class="absolute right-0 z-40 mt-2 w-[min(32rem,calc(100vw-2rem))] space-y-4 rounded-2xl bg-white p-4 shadow-2xl ring-1 ring-slate-900/10">
        @foreach($conserver as $nom => $valeur)
            @if($valeur !== null && $valeur !== '')<input type="hidden" name="{{ $nom }}" value="{{ $valeur }}">@endif
        @endforeach
        <input type="hidden" name="mode" value="{{ $periode->mode }}" data-mode-champ>

        <div class="flex gap-1 rounded-xl bg-slate-100 p-1">
            <button type="button" data-mode-bouton="calendrier" class="{{ $bouton }} {{ $calendrier ? $actifBouton : $inactifBouton }}"
                    data-classe-actif="{{ $actifBouton }}" data-classe-inactif="{{ $inactifBouton }}">Calendrier</button>
            <button type="button" data-mode-bouton="periode" class="{{ $bouton }} {{ $calendrier ? $inactifBouton : $actifBouton }}"
                    data-classe-actif="{{ $actifBouton }}" data-classe-inactif="{{ $inactifBouton }}">Période</button>
        </div>

        {{-- Calendrier : exercice → mois → semaine → jour --}}
        <div data-mode-bloc="calendrier" class="grid grid-cols-2 gap-3" @unless($calendrier) hidden @endunless>
            <div>
                <label class="label text-xs" for="filtre-annee">Exercice</label>
                <select id="filtre-annee" name="annee" data-auto class="{{ $champ }} font-semibold" @disabled(! $calendrier)>
                    @foreach($annees as $a)<option value="{{ $a }}" @selected($a === $periode->annee)>{{ $a }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label text-xs" for="filtre-mois">Mois</label>
                <select id="filtre-mois" name="mois" data-auto data-reinitialiser="semaine,jour" class="{{ $champ }}" @disabled(! $calendrier)>
                    <option value="">Tous les mois</option>
                    @foreach(Periode::MOIS as $n => $nom)<option value="{{ $n }}" @selected($n === $periode->mois)>{{ $nom }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label text-xs" for="filtre-semaine">Semaine</label>
                <select id="filtre-semaine" name="semaine" data-auto data-reinitialiser="jour" class="{{ $champ }}" @disabled(! $calendrier || ! $periode->mois)>
                    <option value="">Toutes les semaines</option>
                    @foreach($semaines as $n => $s)<option value="{{ $n }}" @selected($n === $periode->semaine)>{{ $s['libelle'] }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label text-xs" for="filtre-jour">Jour</label>
                <select id="filtre-jour" name="jour" data-auto class="{{ $champ }}" @disabled(! $calendrier || ! $periode->mois)>
                    <option value="">Tous les jours</option>
                    @foreach($periode->joursProposes() as $n => $libelle)<option value="{{ $n }}" @selected($n === $periode->jour)>{{ $libelle }}</option>@endforeach
                </select>
            </div>
            @unless($periode->mois)
                <p class="col-span-2 text-xs text-slate-500">Choisissez un mois pour filtrer par semaine ou par jour.</p>
            @endunless
        </div>

        {{-- Période précise : du … au --}}
        <div data-mode-bloc="periode" class="space-y-3" @if($calendrier) hidden @endif>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label text-xs" for="filtre-du">Du</label><input id="filtre-du" type="date" name="du" value="{{ $periode->du->toDateString() }}" required class="{{ $champ }}" @disabled($calendrier)></div>
                <div><label class="label text-xs" for="filtre-au">Au</label><input id="filtre-au" type="date" name="au" value="{{ $periode->au->toDateString() }}" required class="{{ $champ }}" @disabled($calendrier)></div>
            </div>
            <button type="submit" class="btn-primary w-full">Appliquer</button>
        </div>

        <a href="{{ url()->current() }}" class="block text-center text-xs font-semibold text-brand-600 hover:underline">Revenir à aujourd'hui</a>
    </form>
</details>
