@props(['nom', 'titre'])
{{-- Section repliable de la barre latérale (état mémorisé sur ce poste) --}}
<div data-menu-section="{{ $nom }}" class="mb-1">
    <button type="button" data-menu-bascule aria-expanded="true" aria-controls="menu-{{ $nom }}"
            class="nav-section group flex w-full items-center justify-between rounded-lg py-1 hover:text-slate-300">
        <span>{{ $titre }}</span>
        <x-icon name="chevron-down" class="size-3.5 transition-transform duration-200 group-aria-[expanded=false]:-rotate-90"/>
    </button>
    <div id="menu-{{ $nom }}" data-menu-liens class="space-y-1">
        {{ $slot }}
    </div>
</div>
