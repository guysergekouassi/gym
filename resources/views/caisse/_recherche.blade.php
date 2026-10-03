{{-- Recherche de client. Variables : $requis (bool), $preselection (?Client) --}}
<div data-recherche-client data-url="{{ route('caisse.clients') }}" class="relative">
    <input type="hidden" name="client_id" data-client-id value="{{ $preselection?->id }}">

    <div data-client-champ class="{{ $preselection ? 'hidden' : '' }}">
        <div class="relative">
            <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-5 -translate-y-1/2 text-slate-400"/>
            <input type="search" data-client-q autocomplete="off" maxlength="100"
                   placeholder="{{ $requis ? 'Rechercher le client : nom, téléphone ou n° empreinte' : 'Client existant (facultatif) : nom, téléphone…' }}"
                   class="input py-3 pl-11">
        </div>
    </div>

    <div data-client-choisi class="{{ $preselection ? '' : 'hidden' }} flex items-center justify-between gap-3 rounded-xl bg-emerald-50 px-4 py-3 ring-1 ring-emerald-600/20">
        <div class="flex items-center gap-3">
            <span class="inline-flex size-9 items-center justify-center rounded-full bg-emerald-600 text-white"><x-icon name="check" class="size-5"/></span>
            <span data-client-choisi-nom class="text-sm font-semibold text-emerald-900">{{ $preselection?->nom_complet }}</span>
        </div>
        <button type="button" data-client-effacer class="text-xs font-semibold text-emerald-800 hover:underline">Changer</button>
    </div>

    <ul data-client-resultats class="absolute z-20 mt-2 hidden max-h-72 w-full divide-y divide-slate-100 overflow-auto rounded-xl bg-white shadow-xl ring-1 ring-slate-200"></ul>
</div>
