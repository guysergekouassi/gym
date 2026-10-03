@props(['parts', 'centre' => '', 'sousTitre' => '', 'afficherValeurs' => true])
{{-- Anneau (SVG) : $parts = [['libelle' =>, 'valeur' =>, 'couleur' =>], …] ; légende avec % à côté. --}}
@php
    $total = max(1, array_sum(array_column($parts, 'valeur')));
    $r = 15.9155; // circonférence = 100
    $ecart = count(array_filter($parts, fn ($p) => $p['valeur'] > 0)) > 1 ? 0.8 : 0;
    $cumul = 0;
@endphp
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-6']) }}>
    <div class="relative size-40 shrink-0 max-sm:mx-auto">
        <svg viewBox="0 0 42 42" class="size-full -rotate-90" role="img" aria-label="{{ $sousTitre }}">
            <circle cx="21" cy="21" r="{{ $r }}" fill="none" stroke="#eef2f6" stroke-width="5.5"/>
            @foreach($parts as $p)
                @php $pct = 100 * $p['valeur'] / $total; @endphp
                @if($p['valeur'] > 0)
                    <circle cx="21" cy="21" r="{{ $r }}" fill="none" stroke="{{ $p['couleur'] }}" stroke-width="5.5"
                            stroke-dasharray="{{ max(0.1, $pct - $ecart) }} {{ 100 - max(0.1, $pct - $ecart) }}" stroke-dashoffset="{{ -$cumul }}">
                        <title>{{ $p['libelle'] }} : {{ $p['valeur'] }} ({{ round($pct) }} %)</title>
                    </circle>
                @endif
                @php $cumul += $pct; @endphp
            @endforeach
        </svg>
        <div class="absolute inset-0 flex flex-col items-center justify-center">
            <span class="text-3xl font-bold text-slate-900">{{ $centre }}</span>
            <span class="text-xs text-slate-500">{{ $sousTitre }}</span>
        </div>
    </div>
    <ul class="min-w-44 flex-1 space-y-3 text-sm">
        @foreach($parts as $p)
            <li class="flex items-center gap-2.5">
                <span class="size-3 shrink-0 rounded-full" style="background: {{ $p['couleur'] }}"></span>
                <span class="min-w-0 flex-1 text-slate-700">{{ $p['libelle'] }}</span>
                @if($afficherValeurs)<span class="font-semibold text-slate-900">{{ $p['valeur'] }}</span>@endif
                <span class="whitespace-nowrap pl-2 text-right font-semibold text-slate-900" title="{{ $p['valeur'] }} client(s)">{{ round(100 * $p['valeur'] / $total) }} %</span>
            </li>
        @endforeach
    </ul>
</div>
