@php use App\Support\Fcfa; @endphp
<div class="card p-5">
    <div class="flex items-center justify-between">
        <p class="text-sm text-slate-600">{{ auth()->user()->isAdmin() ? 'Caisse du jour (tous postes)' : 'Ma caisse aujourd\'hui' }}</p>
        <span class="pill-green">{{ $nombreJour }} ticket(s)</span>
    </div>
    <p class="mt-2 text-2xl font-bold text-slate-900">{{ Fcfa::format($totalJour) }}</p>
    @if($paiements->isNotEmpty())
        <ul class="mt-3 space-y-1.5 border-t border-slate-100 pt-3 text-sm">
            @foreach($paiements->take(4) as $p)
                <li class="flex justify-between gap-2 {{ $p->estAnnule() ? 'text-slate-400 line-through' : '' }}">
                    <a href="{{ route('recus.show', $p) }}" class="truncate text-slate-600 hover:text-brand-600">{{ $p->created_at->format('H:i') }} · {{ $p->client?->nom_complet ?? 'Anonyme' }}</a>
                    <span class="whitespace-nowrap font-medium">{{ Fcfa::format($p->montant) }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
