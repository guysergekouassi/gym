{{-- Choix du client : liste déroulante, champ de recherche en tête de liste. Variables : $requis (bool), $preselection (?Client) --}}
<div data-recherche-client data-url="{{ route('caisse.clients') }}" data-requis="{{ $requis ? '1' : '0' }}" class="relative">
    <input type="hidden" name="client_id" data-client-id value="{{ $preselection?->id }}">

    <button type="button" data-client-ouvrir aria-haspopup="listbox" aria-expanded="false"
            class="input flex items-center justify-between gap-3 py-3 text-left">
        <span class="flex min-w-0 items-center gap-2.5">
            <x-icon name="user" class="size-5 shrink-0 text-slate-400"/>
            <span data-client-libelle data-vide="{{ $requis ? 'Choisir le client…' : 'Aucun client (passage anonyme)' }}"
                  class="truncate {{ $preselection ? 'font-semibold text-slate-900' : 'text-slate-400' }}">
                {{ $preselection?->nom_complet ?? ($requis ? 'Choisir le client…' : 'Aucun client (passage anonyme)') }}
            </span>
        </span>
        <x-icon name="chevron-down" class="size-4 shrink-0 text-slate-500"/>
    </button>

    <div data-client-panneau hidden class="absolute z-30 mt-2 w-full overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-slate-200">
        <div class="border-b border-slate-100 p-2">
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"/>
                <input type="search" data-client-q autocomplete="off" maxlength="100" aria-label="Rechercher un client"
                       placeholder="Rechercher : nom, téléphone ou n° empreinte"
                       class="w-full rounded-lg border-0 bg-slate-50 py-2 pl-9 pr-3 text-sm ring-1 ring-slate-200 focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
        </div>
        <ul data-client-resultats role="listbox" class="defilement-clair max-h-72 divide-y divide-slate-100 overflow-y-auto"></ul>
    </div>
</div>
