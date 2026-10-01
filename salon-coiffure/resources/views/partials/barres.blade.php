{{-- Graphique en barres sans librairie : $points = [['libelle' => ..., 'valeur' => ...]], $format = 'fcfa' | 'nombre' --}}
@php
    $max = max(1, max(array_column($points, 'valeur') ?: [0]));
    $fcfa = ($format ?? 'fcfa') === 'fcfa';
    $court = fn (int $v) => $fcfa
        ? ($v >= 1000000 ? number_format($v / 1000000, 1, ',', ' ').'M' : ($v >= 1000 ? number_format($v / 1000, $v % 1000 ? 1 : 0, ',', ' ').'k' : (string) $v))
        : (string) $v;
@endphp
<div class="barres {{ $classe ?? '' }}">
    @foreach($points as $point)
        <div class="col" title="{{ $point['libelle'] }} : {{ $fcfa ? \App\Support\Fcfa::format($point['valeur']) : $point['valeur'] }}">
            <div class="barre {{ $point['valeur'] ? '' : 'vide-barre' }}" style="height: {{ max(1, round($point['valeur'] * 100 / $max)) }}%">
                @if($point['valeur'] && count($points) <= 16)<span>{{ $court($point['valeur']) }}</span>@endif
            </div>
            <small>{{ $point['libelle'] }}</small>
        </div>
    @endforeach
</div>
