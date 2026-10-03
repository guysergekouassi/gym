@props(['label', 'value', 'icon' => 'chart', 'tone' => 'brand', 'hint' => null])
@php
    $tons = [
        'brand' => 'bg-brand-50 text-brand-600',
        'green' => 'bg-emerald-50 text-emerald-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'blue' => 'bg-sky-50 text-sky-600',
        'red' => 'bg-red-50 text-red-600',
        'slate' => 'bg-slate-100 text-slate-600',
    ];
@endphp
<div {{ $attributes->merge(['class' => 'card p-5']) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
            <p class="mt-2 text-2xl font-bold tracking-tight whitespace-nowrap text-slate-900">{{ $value }}</p>
        </div>
        <span class="inline-flex size-11 shrink-0 items-center justify-center rounded-xl {{ $tons[$tone] ?? $tons['brand'] }}">
            <x-icon :name="$icon" class="size-6"/>
        </span>
    </div>
    @if($hint)<p class="mt-3 text-xs text-slate-500">{{ $hint }}</p>@endif
</div>
