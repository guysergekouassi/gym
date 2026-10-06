@props(['class' => 'h-9 w-9'])
{{-- Pictogramme du Gymnase EPIKAÏZO --}}
<img src="{{ asset('images/picto-epikaizo.png') }}" alt="" aria-hidden="true" style="object-fit: contain" {{ $attributes->merge(['class' => "$class shrink-0"]) }}>
