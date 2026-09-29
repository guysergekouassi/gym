@extends('layouts.app')
@section('title', 'Journal des actions')

@php use App\Support\Journal; @endphp

@section('content')
<div class="top"><div><div class="eyebrow">Annulations, clôtures, modifications : qui a fait quoi et quand</div><h1>Journal des actions</h1></div></div>

<form method="GET" class="filters">
    <label class="fld" for="action" style="flex:0 1 260px">Action
        <select id="action" name="action"><option value="">Toutes</option>@foreach(Journal::ACTIONS as $v => $l)<option value="{{ $v }}" @selected(request('action') === $v)>{{ $l }}</option>@endforeach</select>
    </label>
    <label class="fld" for="user_id" style="flex:0 1 240px">Personne
        <select id="user_id" name="user_id"><option value="">Tout le monde</option>@foreach($utilisateurs as $u)<option value="{{ $u->id }}" @selected((int) request('user_id') === $u->id)>{{ $u->name }}</option>@endforeach</select>
    </label>
    <button class="btn ghost">Filtrer</button>
</form>

<div class="table-wrap">
    <table>
        <thead><tr><th>Date</th><th>Personne</th><th>Action</th><th>Détail</th></tr></thead>
        <tbody>
        @forelse($entrees as $e)
            <tr>
                <td class="time" style="white-space:nowrap">{{ $e->created_at->format('d/m/Y H:i') }}</td>
                <td>{{ $e->user?->name ?? 'Système' }}</td>
                <td><span class="tag {{ str_starts_with($e->action, 'paiement.') ? 'ko' : 'info' }}">{{ Journal::ACTIONS[$e->action] ?? $e->action }}</span></td>
                <td>{{ $e->resume }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">Aucune action enregistrée.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="pager">{{ $entrees->links('partials.pagination') }}</div>
@endsection
