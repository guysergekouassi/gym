@props(['nom', 'titre'])
{{-- Section repliable de la barre latérale : le bouton à droite du titre ouvre / ferme la section --}}
<div data-menu-section="{{ $nom }}" class="mt-4 first:mt-0">
    <button type="button" data-menu-bascule aria-expanded="true" aria-controls="menu-{{ $nom }}"
            title="Ouvrir / fermer la section {{ $titre }}"
            class="group mb-1.5 flex w-full items-center justify-between rounded-lg px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-400 transition hover:bg-white/5 hover:text-white">
        <span>{{ $titre }}</span>
        <span class="inline-flex size-6 items-center justify-center rounded-md bg-white/10 text-slate-200 ring-1 ring-white/10 transition group-hover:bg-brand-500 group-hover:text-white">
            <x-icon name="chevron-down" class="size-3.5 transition-transform duration-200 group-aria-[expanded=false]:-rotate-90"/>
        </span>
    </button>
    <div id="menu-{{ $nom }}" data-menu-liens class="space-y-1">
        {{ $slot }}
    </div>
</div>
