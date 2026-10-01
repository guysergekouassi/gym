@php $p = $periode->type; $route = $route ?? 'dashboard'; @endphp
<div class="filtres-dates">
    <div class="onglets">
        <a href="{{ route($route, ['periode' => 'jour'] + ($extra ?? [])) }}" class="{{ $p === 'jour' ? 'actif' : '' }}">Aujourd'hui</a>
        <a href="{{ route($route, ['periode' => 'semaine'] + ($extra ?? [])) }}" class="{{ $p === 'semaine' ? 'actif' : '' }}">Semaine</a>
        <a href="{{ route($route, ['periode' => 'mois'] + ($extra ?? [])) }}" class="{{ $p === 'mois' ? 'actif' : '' }}">Mois</a>
    </div>
    <form method="GET" action="{{ route($route) }}" class="filtres-dates">
        <input type="hidden" name="periode" value="dates">
        @foreach($extra ?? [] as $cle => $valeur)<input type="hidden" name="{{ $cle }}" value="{{ $valeur }}">@endforeach
        <input type="date" name="du" value="{{ $periode->debut->format('Y-m-d') }}" aria-label="Du">
        <input type="date" name="au" value="{{ $periode->fin->format('Y-m-d') }}" aria-label="Au">
        <button class="btn btn-petit {{ $p === 'dates' ? 'btn-primaire' : '' }}" type="submit">Afficher</button>
    </form>
</div>
