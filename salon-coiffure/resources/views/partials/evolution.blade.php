@if($valeur === null)
    <span class="evol neutre">— pas de comparaison ({{ $libelle }})</span>
@elseif($valeur > 0)
    <span class="evol hausse">▲ +{{ number_format($valeur, 1, ',', ' ') }} % vs {{ $libelle }}</span>
@elseif($valeur < 0)
    <span class="evol baisse">▼ {{ number_format($valeur, 1, ',', ' ') }} % vs {{ $libelle }}</span>
@else
    <span class="evol neutre">= stable vs {{ $libelle }}</span>
@endif
