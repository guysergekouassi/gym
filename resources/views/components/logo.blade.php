@props(['class' => 'size-10'])
<span {{ $attributes->merge(['class' => "$class inline-flex shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-brand-400 to-brand-600 text-white shadow-lg shadow-brand-500/30"]) }}>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" class="size-3/5" aria-hidden="true">
        <path d="M6.5 7v10M17.5 7v10M3.5 9.5v5M20.5 9.5v5M6.5 12h11"/>
    </svg>
</span>
