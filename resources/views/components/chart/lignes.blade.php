@props(['etiquettes', 'series', 'titre' => 'Graphique', 'hauteur' => 240, 'largeur' => 640])
{{--
    Courbes (SVG, sans librairie). $series = [['nom' => …, 'couleur' => '#…', 'valeurs' => [...]], …]
    Survol : infobulle native sur chaque point. Une table équivalente est fournie aux lecteurs d'écran.
--}}
@php
    $L = (int) $largeur; $H = (int) $hauteur; $g = 52; $d = 28; $h = 14; $b = 30;
    $max = max(1, ...array_merge(...array_map(fn ($s) => $s['valeurs'], $series)));
    $pas = 10 ** floor(log10($max));
    foreach ([1, 2, 2.5, 5, 10] as $m) { if ($m * $pas * 4 >= $max) { $echelle = $m * $pas * 4; break; } }
    $n = count($etiquettes);
    $x = fn ($i) => $g + ($n > 1 ? $i * ($L - $g - $d) / ($n - 1) : ($L - $g - $d) / 2);
    $y = fn ($v) => $h + ($H - $h - $b) * (1 - $v / $echelle);
    // Chemin lissé passant par les points, sans « bosse » inventée (interpolation monotone de Fritsch-Carlson)
    $lisse = function (array $pts): string {
        $n = count($pts);
        if ($n < 3) {
            return 'M'.implode(' L', array_map(fn ($p) => round($p[0], 1).','.round($p[1], 1), $pts));
        }
        $d = []; $m = [];
        for ($i = 0; $i < $n - 1; $i++) {
            $d[$i] = ($pts[$i + 1][1] - $pts[$i][1]) / max(0.0001, $pts[$i + 1][0] - $pts[$i][0]);
        }
        $m[0] = $d[0]; $m[$n - 1] = $d[$n - 2];
        for ($i = 1; $i < $n - 1; $i++) {
            $m[$i] = $d[$i - 1] * $d[$i] <= 0 ? 0 : ($d[$i - 1] + $d[$i]) / 2;
        }
        for ($i = 0; $i < $n - 1; $i++) {
            if ($d[$i] == 0) { $m[$i] = $m[$i + 1] = 0; continue; }
            $a = $m[$i] / $d[$i]; $b = $m[$i + 1] / $d[$i]; $h = hypot($a, $b);
            if ($h > 3) { $t = 3 / $h; $m[$i] = $t * $a * $d[$i]; $m[$i + 1] = $t * $b * $d[$i]; }
        }
        $chemin = 'M'.round($pts[0][0], 1).','.round($pts[0][1], 1);
        for ($i = 0; $i < $n - 1; $i++) {
            $dx = ($pts[$i + 1][0] - $pts[$i][0]) / 3;
            $chemin .= sprintf(' C%.1f,%.1f %.1f,%.1f %.1f,%.1f',
                $pts[$i][0] + $dx, $pts[$i][1] + $m[$i] * $dx,
                $pts[$i + 1][0] - $dx, $pts[$i + 1][1] - $m[$i + 1] * $dx,
                $pts[$i + 1][0], $pts[$i + 1][1]);
        }

        return $chemin;
    };
    $court = fn ($v) => $v >= 1000000 ? round($v / 1000000, 1).'M' : ($v >= 1000 ? round($v / 1000).'k' : (string) $v);
@endphp
<figure {{ $attributes }}>
    <svg viewBox="0 0 {{ $L }} {{ $H }}" class="h-auto w-full" role="img" aria-label="{{ $titre }}">
        @for($k = 0; $k <= 4; $k++)
            @php $v = $echelle * $k / 4; @endphp
            <line x1="{{ $g }}" x2="{{ $L - $d }}" y1="{{ $y($v) }}" y2="{{ $y($v) }}" stroke="#e8edf3" stroke-width="1"/>
            <text x="{{ $g - 8 }}" y="{{ $y($v) + 4 }}" text-anchor="end" font-size="13" fill="#64748b">{{ $court($v) }}</text>
        @endfor
        @php $pasEtiquettes = max(1, (int) ceil($n / 12)); @endphp
        @foreach($etiquettes as $i => $etiquette)
            @continue($i % $pasEtiquettes !== 0 && $i !== $n - 1)
            <text x="{{ $x($i) }}" y="{{ $H - 8 }}" text-anchor="middle" font-size="13" fill="#64748b">{{ $etiquette }}</text>
        @endforeach
        @foreach($series as $s)
            @php
                $pts = collect($s['valeurs'])->map(fn ($v, $i) => [$x($i), $y($v)])->all();
                $courbe = $lisse($pts);
                $aire = $courbe.' L'.round($x($n - 1), 1).','.$y(0).' L'.round($x(0), 1).','.$y(0).' Z';
            @endphp
            <path d="{{ $aire }}" fill="{{ $s['couleur'] }}" fill-opacity="0.08"/>
            <path d="{{ $courbe }}" fill="none" stroke="{{ $s['couleur'] }}" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
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
