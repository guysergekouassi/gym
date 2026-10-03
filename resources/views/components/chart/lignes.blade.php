@props(['etiquettes', 'series', 'titre' => 'Graphique'])
{{--
    Courbes (SVG, sans librairie). $series = [['nom' => …, 'couleur' => '#…', 'valeurs' => [...]], …]
    Survol : infobulle native sur chaque point. Une table équivalente est fournie aux lecteurs d'écran.
--}}
@php
    $L = 640; $H = 240; $g = 48; $d = 16; $h = 14; $b = 30;
    $max = max(1, ...array_merge(...array_map(fn ($s) => $s['valeurs'], $series)));
    $pas = 10 ** floor(log10($max));
    foreach ([1, 2, 2.5, 5, 10] as $m) { if ($m * $pas * 4 >= $max) { $echelle = $m * $pas * 4; break; } }
    $n = count($etiquettes);
    $x = fn ($i) => $g + ($n > 1 ? $i * ($L - $g - $d) / ($n - 1) : ($L - $g - $d) / 2);
    $y = fn ($v) => $h + ($H - $h - $b) * (1 - $v / $echelle);
    $court = fn ($v) => $v >= 1000000 ? round($v / 1000000, 1).'M' : ($v >= 1000 ? round($v / 1000).'k' : (string) $v);
@endphp
<figure {{ $attributes }}>
    <svg viewBox="0 0 {{ $L }} {{ $H }}" class="h-auto w-full" role="img" aria-label="{{ $titre }}">
        @for($k = 0; $k <= 4; $k++)
            @php $v = $echelle * $k / 4; @endphp
            <line x1="{{ $g }}" x2="{{ $L - $d }}" y1="{{ $y($v) }}" y2="{{ $y($v) }}" stroke="#e8edf3" stroke-width="1"/>
            <text x="{{ $g - 8 }}" y="{{ $y($v) + 4 }}" text-anchor="end" font-size="11" fill="#64748b">{{ $court($v) }}</text>
        @endfor
        @foreach($etiquettes as $i => $etiquette)
            <text x="{{ $x($i) }}" y="{{ $H - 8 }}" text-anchor="middle" font-size="11" fill="#64748b">{{ $etiquette }}</text>
        @endforeach
        @foreach($series as $s)
            @php
                $points = collect($s['valeurs'])->map(fn ($v, $i) => round($x($i), 1).','.round($y($v), 1))->implode(' ');
                $aire = round($x(0), 1).','.$y(0).' '.$points.' '.round($x($n - 1), 1).','.$y(0);
            @endphp
            <polygon points="{{ $aire }}" fill="{{ $s['couleur'] }}" fill-opacity="0.08"/>
            <polyline points="{{ $points }}" fill="none" stroke="{{ $s['couleur'] }}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>
            @foreach($s['valeurs'] as $i => $v)
                <g>
                    <circle cx="{{ $x($i) }}" cy="{{ $y($v) }}" r="12" fill="transparent"><title>{{ $etiquettes[$i] }} · {{ $s['nom'] }} : {{ number_format($v, 0, ',', ' ') }} FCFA</title></circle>
                    <circle cx="{{ $x($i) }}" cy="{{ $y($v) }}" r="4" fill="{{ $s['couleur'] }}" stroke="#fff" stroke-width="2" pointer-events="none"/>
                </g>
            @endforeach
        @endforeach
    </svg>
    <table class="sr-only">
        <caption>{{ $titre }}</caption>
        <tr><th>Jour</th>@foreach($series as $s)<th>{{ $s['nom'] }}</th>@endforeach</tr>
        @foreach($etiquettes as $i => $e)<tr><td>{{ $e }}</td>@foreach($series as $s)<td>{{ $s['valeurs'][$i] }}</td>@endforeach</tr>@endforeach
    </table>
</figure>
