@extends('layouts.app')
@section('title', $caissier->exists ? 'Modifier la caissière' : 'Nouvelle caissière')

@section('content')
<div class="top">
    <div>
        <div class="eyebrow"><a href="{{ route('admin.caissiers.index') }}">← Caissières</a></div>
        <h1>{{ $caissier->exists ? $caissier->name : 'Nouvelle caissière' }}</h1>
    </div>
</div>

@if($caisses->isEmpty())
    <section class="card form-card">
        <h2>Créez d'abord une caisse</h2>
        <p class="empty">Chaque caissière encaisse sur une caisse. Aucune caisse active n'existe pour l'instant.</p>
        <a href="{{ route('admin.caisses.create') }}" class="btn" style="align-self:flex-start">Créer une caisse</a>
    </section>
@else
<form method="POST" action="{{ $caissier->exists ? route('admin.caissiers.update', $caissier) : route('admin.caissiers.store') }}" class="card form-card">
    @csrf
    @if($caissier->exists) @method('PUT') @endif

    <div class="grid g2">
        <label class="fld" for="name">Nom affiché *
            <input id="name" type="text" name="name" required maxlength="100" value="{{ old('name', $caissier->name) }}" placeholder="ex. Awa Koné">
        </label>
        <label class="fld" for="email">E-mail de connexion *
            <input id="email" type="email" name="email" required maxlength="150" value="{{ old('email', $caissier->email) }}" autocomplete="off">
        </label>
        <label class="fld" for="caisse_id">Caisse attribuée *
            <select id="caisse_id" name="caisse_id" required>
                @foreach($caisses as $caisse)
                    <option value="{{ $caisse->id }}" @selected((int) old('caisse_id', $caissier->caisse_id) === $caisse->id)>{{ $caisse->nom }}{{ $caisse->actif ? '' : ' (désactivée)' }}</option>
                @endforeach
            </select>
        </label>
        <div></div>
        <label class="fld" for="password">{{ $caissier->exists ? 'Nouveau mot de passe' : 'Mot de passe *' }}
            <input id="password" type="password" name="password" minlength="8" autocomplete="new-password" @required(! $caissier->exists)>
            <span class="aide">{{ $caissier->exists ? 'Laissez vide pour garder le mot de passe actuel.' : '8 caractères minimum. Communiquez-le à la caissière en main propre.' }}</span>
        </label>
        <label class="fld" for="password_confirmation">Confirmer le mot de passe
            <input id="password_confirmation" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" @required(! $caissier->exists)>
        </label>
    </div>

    <label class="check" for="actif">
        <input id="actif" type="checkbox" name="actif" value="1" @checked(old('actif', $caissier->actif))>
        Compte actif (peut se connecter)
    </label>

    <div class="actions">
        <button class="btn">{{ $caissier->exists ? 'Enregistrer' : 'Créer le compte' }}</button>
        <a href="{{ route('admin.caissiers.index') }}" class="btn ghost">Annuler</a>
    </div>
</form>
@endif
@endsection
