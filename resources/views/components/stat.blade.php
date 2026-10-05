@props(['label', 'value', 'icon' => 'chart', 'tone' => 'brand', 'hint' => null])
@php $tonsKpi = ['brand' => 'green', 'green' => 'green', 'amber' => 'orange', 'blue' => 'blue', 'red' => 'red', 'slate' => 'slate']; @endphp
<x-kpi :label="$label" :value="$value" :icon="$icon" :tone="$tonsKpi[$tone] ?? 'green'" :hint="$hint" {{ $attributes }}/>
