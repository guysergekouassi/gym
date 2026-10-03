@extends('layouts.app')
@section('title', 'Utilisateurs')

@php use App\Models\User; @endphp

@section('content')
<x-page-header title="Utilisateurs" subtitle="Comptes du personnel. Une caissière n'a accès qu'à la caisse, aux clients et à l'écran d'accueil.">
    <a href="{{ route('admin.utilisateurs.create') }}" class="btn-primary"><x-icon name="user-plus" class="size-4"/> Nouveau compte</a>
</x-page-header>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="table">
            <thead><tr><th>Nom</th><th>Rôle</th><th>Statut</th><th>Dernière connexion</th><th></th></tr></thead>
            <tbody>
            @foreach($utilisateurs as $u)
                <tr class="{{ $u->actif ? '' : 'opacity-60' }}">
                    <td>
                        <div class="flex items-center gap-3">
                            <span class="inline-flex size-10 items-center justify-center rounded-full {{ $u->isAdmin() ? 'bg-ink-900 text-white' : 'bg-brand-100 text-brand-700' }} text-sm font-bold">{{ mb_strtoupper(mb_substr($u->name, 0, 1)) }}</span>
                            <span><span class="block font-semibold text-slate-900">{{ $u->name }}@if($u->is(auth()->user())) <span class="text-xs font-normal text-slate-400">(vous)</span>@endif</span><span class="block text-xs text-slate-500">{{ $u->email }}</span></span>
                        </div>
                    </td>
                    <td><span class="{{ $u->isAdmin() ? 'pill-brand' : 'pill-blue' }}">{{ User::ROLES[$u->role] ?? $u->role }}</span></td>
                    <td>
                        @if(! $u->actif)<span class="pill-gray">Désactivé</span>
                        @elseif($u->doit_changer_mdp)<span class="pill-amber">Mot de passe provisoire</span>
                        @else<span class="pill-green">Actif</span>@endif
                    </td>
                    <td class="text-slate-600">{{ $u->derniere_connexion_at?->diffForHumans() ?? 'Jamais' }}</td>
                    <td class="text-right"><a href="{{ route('admin.utilisateurs.edit', $u) }}" class="btn-light btn-sm"><x-icon name="pencil" class="size-3.5"/> Modifier</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
