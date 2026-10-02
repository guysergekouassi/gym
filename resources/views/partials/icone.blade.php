{{-- Icônes au trait : @include('partials.icone', ['nom' => 'caisse']) --}}
<svg width="{{ $taille ?? 18 }}" height="{{ $taille ?? 18 }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $trait ?? 2 }}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($nom)
    @case('tableau')<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>@break
    @case('caisse')<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M7 15h4"/>@break
    @case('clients')<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.5 3.4-5.5 6.5-5.5s5.7 2 6.5 5.5M16 4.5a3.5 3.5 0 0 1 0 7M18.5 14.8c1.6.8 2.7 2.6 3 5.2"/>@break
    @case('passages')<path d="M8 3v3M16 3v3M3 9h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2zM8 14l2.5 2.5L16 12"/>@break
    @case('empreinte')<path d="M12 3c-3 0-5.5 2.5-5.5 5.5v3M17.5 8.5v4c0 3.5-2 6.5-5.5 8.5M9 12v1.5c0 2-1 3.5-2.5 4.5M12 8.5v5c0 2.5-1 4.5-3 6"/>@break
    @case('equipe')<circle cx="12" cy="7" r="3.5"/><path d="M5 21c.8-4 3.6-6.5 7-6.5s6.2 2.5 7 6.5"/>@break
    @case('plus')<path d="M12 5v14M5 12h14"/>@break
    @case('imprimer')<path d="M6 9V3h12v6M6 18H4v-7h16v7h-2M7 14h10v7H7z"/>@break
    @case('gauche')<path d="M15 5l-7 7 7 7"/>@break
    @case('droite')<path d="M9 5l7 7-7 7"/>@break
    @case('ecran')<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>@break
    @case('message')<path d="M21 11.5a8.5 8.5 0 0 1-12.6 7.4L3 20.5l1.7-5.2A8.5 8.5 0 1 1 21 11.5z"/>@break
    @case('haltere')<path d="M6 7v10M18 7v10M3 10v4M21 10v4M6 12h12"/>@break
    @case('biceps')
    @case('epikaizo')<circle cx="10" cy="5" r="2.5"/><path d="M4 17.5c1.5-4.5 4.5-7 8.5-7 1.8 0 3 .6 4 1.8l1.5-2.2c.6-.9 1.6-1.1 2.5-.6.8.5 1 1.5.7 2.3l-.7 2.2c1.3.8 2 2.2 1.5 3.7-.5 1.6-2 2.8-3.8 2.8h-1.2c-.8 2-2.5 3.5-4.8 3.8-3 .4-6.2-.8-8.2-3.8z"/>@break
    @case('loupe')<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>@break
    @case('liste')<path d="M9 6h11M9 12h11M9 18h11M4 6l1 1 2-2M4 12l1 1 2-2M4 18l1 1 2-2"/>@break
    @case('prospect')<circle cx="10" cy="8" r="3.5"/><path d="M3.5 20c.8-3.5 3.4-5.5 6.5-5.5 1.3 0 2.5.3 3.5 1M18 14v6M15 17h6"/>@break
    @case('calendrier')<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18M8 14h2M14 14h2M8 17h2"/>@break
    @case('etiquette')<path d="M3 12V4h8l10 10-8 8L3 12z"/><circle cx="7.5" cy="8.5" r="1.5"/>@break
    @case('coach')<circle cx="12" cy="5" r="2.5"/><path d="M12 8v6M8 22l4-8 4 8M6 11l6-1 6 1"/>@break
    @case('boutique')<path d="M4 8h16l-1 12H5L4 8zM9 8V6a3 3 0 0 1 6 0v2"/>@break
    @case('salle')<path d="M3 21V9l9-6 9 6v12M9 21v-6h6v6"/>@break
    @case('graphique')<path d="M4 20V4M4 20h16M8 16v-4M12 16V8M16 16v-6"/>@break
    @case('export')<path d="M12 3v12M7 10l5 5 5-5M4 19h16"/>@break
    @case('journal')<path d="M6 3h11a2 2 0 0 1 2 2v16l-3-2-3 2-3-2-3 2V5a2 2 0 0 1 2-2zM9 8h6M9 12h6"/>@break
    @case('cadenas')<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>@break
    @case('flocon')<path d="M12 2v20M4 7l16 10M20 7L4 17M9 4l3 2 3-2M9 20l3-2 3 2"/>@break
    @case('carte')<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M7 14h4"/>@break
    @case('whatsapp')<path d="M21 11.5a8.5 8.5 0 0 1-12.6 7.4L3 20.5l1.7-5.2A8.5 8.5 0 1 1 21 11.5z"/>@break
    @case('annuler')<circle cx="12" cy="12" r="9"/><path d="M8 8l8 8"/>@break
@endswitch
</svg>
