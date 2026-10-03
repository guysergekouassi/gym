@props(['client', 'size' => 'size-10', 'text' => 'text-sm'])
@if($client->photo_url)
    <img src="{{ $client->photo_url }}" alt="" {{ $attributes->merge(['class' => "$size shrink-0 rounded-full object-cover ring-2 ring-white"]) }}>
@else
    @php
        $teintes = ['bg-brand-100 text-brand-700', 'bg-sky-100 text-sky-700', 'bg-emerald-100 text-emerald-700', 'bg-violet-100 text-violet-700', 'bg-amber-100 text-amber-700'];
        $teinte = $teintes[crc32((string) $client->nom) % count($teintes)];
    @endphp
    <span {{ $attributes->merge(['class' => "$size $text $teinte inline-flex shrink-0 items-center justify-center rounded-full font-bold"]) }}>
        {{ mb_strtoupper(mb_substr((string) $client->nom, 0, 1).mb_substr((string) $client->prenoms, 0, 1)) }}
    </span>
@endif
