@props(['label', 'value', 'icon' => 'chart', 'tone' => 'green', 'variation' => null, 'reference' => 'par rapport à hier', 'hint' => null, 'comparable' => false])
@php
    $tons = [
        'green' => 'bg-emerald-50 text-emerald-600',
        'purple' => 'bg-violet-50 text-violet-600',
        'blue' => 'bg-sky-50 text-sky-600',
        'orange' => 'bg-orange-50 text-orange-500',
        'red' => 'bg-red-50 text-red-600',
        'slate' => 'bg-slate-100 text-slate-600',
    ];
@endphp
{{-- La taille du chiffre s'adapte à la largeur de la carte (requêtes de conteneur) : il ne déborde jamais --}}
<div {{ $attributes->merge(['class' => 'card @container p-4 2xl:p-5']) }}>
    <div class="flex gap-3">
        <span class="pastille size-10 @[15rem]:size-12 {{ $tons[$tone] ?? $tons['green'] }}"><x-icon :name="$icon" class="size-6"/></span>
        <div class="min-w-0 flex-1">
            <p class="text-[13px] font-medium leading-snug text-slate-600 @[15rem]:text-sm">{{ $label }}</p>
            <p class="mt-1 whitespace-nowrap text-[clamp(0.8rem,8.5cqi,1.6rem)] font-bold leading-tight tracking-tight text-slate-900">{{ $value }}</p>
            @if($variation !== null)
                <p class="mt-2">
                    @if($variation == 0)
                        <span class="tendance-stable">= 0 %</span>
                    @elseif($variation > 0)
                        <span class="tendance-hausse"><x-icon name="arrow-up" class="size-3.5"/> +{{ $variation }} %</span>
                    @else
                        <span class="tendance-baisse"><x-icon name="arrow-down" class="size-3.5"/> {{ $variation }} %</span>
                    @endif
                </p>
                <p class="text-xs text-slate-500">{{ $reference }}</p>
            @elseif($hint)
                <p class="mt-2 text-xs text-slate-500">{{ $hint }}</p>
            @elseif($comparable)
                {{-- Rien à comparer (aucune donnée sur la période précédente) : pas de pourcentage trompeur --}}
                <p class="mt-2"><span class="tendance-stable">—</span></p>
                <p class="text-xs text-slate-500">rien à comparer {{ str_replace('par rapport à', 'avec', $reference) }}</p>
            @endif
        </div>
    </div>
</div>
