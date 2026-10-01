@extends('layouts.app')

@section('titre', 'Mon mot de passe')

@section('contenu')
<div class="page-titre"><div><h2>@include('partials.icone', ['nom' => 'cle']) Changer mon mot de passe</h2></div></div>

<div class="carte" style="max-width:480px">
    <form method="POST" action="{{ route('mot-de-passe.update') }}" class="carte-corps">
        @csrf @method('PUT')
        <div class="champ">
            <label for="actuel">Mot de passe actuel</label>
            <input type="password" id="actuel" name="actuel" required autocomplete="current-password">
            @error('actuel')<div class="erreur">{{ $message }}</div>@enderror
        </div>
        <div class="champ">
            <label for="password">Nouveau mot de passe</label>
            <input type="password" id="password" name="password" required minlength="6" autocomplete="new-password">
            @error('password')<div class="erreur">{{ $message }}</div>@enderror
        </div>
        <div class="champ">
            <label for="password_confirmation">Confirmer</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required minlength="6" autocomplete="new-password">
        </div>
        <button class="btn btn-primaire" type="submit">Enregistrer</button>
    </form>
</div>
@endsection
