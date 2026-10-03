@props(['label', 'value', 'icon' => 'chart', 'tone' => 'green', 'variation' => null, 'reference' => 'par rapport à hier', 'hint' => null])
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
<div {{ $attributes->merge(['class' => 'card @container p-5']) }}>
    <div class="flex flex-col gap-3 @[12rem]:flex-row @[12rem]:gap-3.5">
        <span class="pastille size-11 {{ $tons[$tone] ?? $tons['green'] }}"><x-icon :name="$icon" class="size-6"/></span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-medium leading-snug text-slate-600">{{ $label }}</p>
            <p class="mt-1 break-words text-lg font-bold leading-tight tracking-tight text-slate-900 @[17rem]:text-xl @[20rem]:text-2xl">{{ $value }}</p>
            @if($variation !== null)
                <p class="mt-2">
                    @if($variation >= 0)
                        <span class="tendance-hausse"><x-icon name="arrow-up" class="size-3.5"/> +{{ $variation }} %</span>
                    @else
                        <span class="tendance-baisse"><x-icon name="arrow-down" class="size-3.5"/> {{ $variation }} %</span>
                    @endif
                </p>
                <p class="text-xs text-slate-500">{{ $reference }}</p>
            @elseif($hint)
                <p class="mt-2 text-xs text-slate-500">{{ $hint }}</p>
            @endif
        </div>
    </div>
</div>
