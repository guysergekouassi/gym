@php use App\Support\Fcfa; @endphp
@if($ventes->isEmpty())
    <div class="vide">Aucun ticket sur la période.</div>
@else
<div style="overflow-x:auto">
<table class="tableau">
    <thead>
        <tr><th>Ticket</th><th>Date</th><th>Services</th><th>Par</th><th>Paiement</th><th class="right">Total</th><th class="right">Actions</th></tr>
    </thead>
    <tbody>
    @foreach($ventes as $vente)
        <tr class="{{ $vente->estAnnulee() ? 'annule' : '' }}">
            <td class="mono nowrap"><b>{{ $vente->numero }}</b></td>
            <td class="nowrap">{{ $vente->created_at->format('d/m H:i') }}</td>
            <td class="small">{{ $vente->relationLoaded('lignes') ? $vente->lignes->map(fn ($l) => ($l->quantite > 1 ? $l->quantite.'× ' : '').$l->libelle)->implode(', ') : '' }}</td>
            <td>{{ $vente->user?->name }}</td>
            <td>{{ $vente->libelleModePaiement() }}</td>
            <td class="right nowrap"><b>{{ Fcfa::format($vente->total) }}</b></td>
            <td class="right nowrap garder">
                <a class="btn btn-petit" href="{{ route('tickets.show', $vente) }}">@include('partials.icone', ['nom' => 'imprimante']) Voir</a>
                @if($vente->estAnnulee())
                    <span class="badge badge-rouge" title="{{ $vente->motif_annulation }}">Annulé</span>
                @else
                    <form method="POST" action="{{ route('ventes.annuler', $vente) }}" style="display:inline"
                          onsubmit="const m = prompt('Motif de l\'annulation du ticket {{ $vente->numero }} ?'); if (!m) return false; this.motif.value = m;">
                        @csrf
                        <input type="hidden" name="motif">
                        <button class="btn btn-petit btn-rouge" type="submit">Annuler</button>
                    </form>
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
@endif
