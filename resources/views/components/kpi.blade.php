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
<div {{ $attributes->merge(['class' => 'card flex gap-4 p-5']) }}>
    <span class="pastille size-11 {{ $tons[$tone] ?? $tons['green'] }}"><x-icon :name="$icon" class="size-6"/></span>
    <div class="min-w-0">
        <p class="text-sm font-medium text-slate-600">{{ $label }}</p>
        <p class="mt-1 whitespace-nowrap text-xl font-bold tracking-tight text-slate-900 2xl:text-2xl">{{ $value }}</p>
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
