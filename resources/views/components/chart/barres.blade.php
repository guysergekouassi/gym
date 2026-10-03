@props(['etiquettes', 'valeurs', 'couleur' => '#0f9960', 'titre' => 'Graphique', 'unite' => 'FCFA'])
{{-- Histogramme (SVG) : barres fines, haut arrondi, infobulle au survol. --}}
@php
    $L = 640; $H = 240; $g = 48; $d = 16; $h = 14; $b = 30;
    $max = max(1, ...$valeurs);
    $pas = 10 ** floor(log10($max));
    foreach ([1, 2, 2.5, 5, 10] as $m) { if ($m * $pas * 4 >= $max) { $echelle = $m * $pas * 4; break; } }
    $n = count($valeurs);
    $bande = ($L - $g - $d) / max(1, $n);
    $largeur = min(28, $bande * 0.55);
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
        @foreach($valeurs as $i => $v)
            @php
                $cx = $g + $bande * ($i + 0.5); $x0 = $cx - $largeur / 2; $top = $y($v); $bas = $y(0);
                $r = min(4, max(0, $bas - $top));
            @endphp
            <g>
                <rect x="{{ $cx - $bande / 2 }}" y="{{ $h }}" width="{{ $bande }}" height="{{ $bas - $h }}" fill="transparent"><title>{{ $etiquettes[$i] }} : {{ number_format($v, 0, ',', ' ') }} {{ $unite }}</title></rect>
                @if($v > 0)
                    <path pointer-events="none" fill="{{ $couleur }}" d="M{{ $x0 }},{{ $bas }} V{{ $top + $r }} Q{{ $x0 }},{{ $top }} {{ $x0 + $r }},{{ $top }} H{{ $x0 + $largeur - $r }} Q{{ $x0 + $largeur }},{{ $top }} {{ $x0 + $largeur }},{{ $top + $r }} V{{ $bas }} Z"/>
                @endif
            </g>
            <text x="{{ $cx }}" y="{{ $H - 8 }}" text-anchor="middle" font-size="11" fill="#64748b">{{ $etiquettes[$i] }}</text>
        @endforeach
    </svg>
    <table class="sr-only">
        <caption>{{ $titre }}</caption>
        @foreach($etiquettes as $i => $e)<tr><td>{{ $e }}</td><td>{{ $valeurs[$i] }}</td></tr>@endforeach
    </table>
</figure>
