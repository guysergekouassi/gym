@extends('layouts.app')

@section('titre', 'Comptes assistantes')

@section('contenu')
<div class="page-titre">
    <div>
        <h2>@include('partials.icone', ['nom' => 'utilisateurs']) Comptes assistantes</h2>
        <p>{{ $actives }} / {{ $maximum }} comptes actifs · une assistante accède uniquement à la caisse et à ses propres KPI.</p>
    </div>
</div>

<div class="grille-dash">
    <div class="carte">
        @if($assistantes->isEmpty())
            <div class="vide">Aucune assistante pour le moment.</div>
        @else
        <table class="tableau">
            <thead><tr><th>Nom</th><th>E-mail</th><th>Tickets</th><th>Statut</th><th class="right">Actions</th></tr></thead>
            <tbody>
            @foreach($assistantes as $a)
                <tr>
                    <td><b>{{ $a->name }}</b></td>
                    <td>{{ $a->email }}</td>
                    <td>{{ $a->ventes_count }}</td>
                    <td>@if($a->actif)<span class="badge badge-vert">Actif</span>@else<span class="badge badge-gris">Désactivé</span>@endif</td>
                    <td class="right nowrap">
                        <form method="POST" action="{{ route('comptes.mot-de-passe', $a) }}" style="display:inline"
                              onsubmit="const m = prompt('Nouveau mot de passe pour {{ addslashes($a->name) }} (6 caractères minimum) :'); if (!m) return false; this.password.value = m;">
                            @csrf @method('PUT')
                            <input type="hidden" name="password">
                            <button class="btn btn-petit" type="submit">@include('partials.icone', ['nom' => 'cle']) Mot de passe</button>
                        </form>
                        <form method="POST" action="{{ route('comptes.activation', $a) }}" style="display:inline">
                            @csrf @method('PATCH')
                            <button class="btn btn-petit {{ $a->actif ? 'btn-rouge' : '' }}" type="submit">{{ $a->actif ? 'Désactiver' : 'Réactiver' }}</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
        @error('password')<div class="alerte alerte-erreur" style="margin:16px">{{ $message }}</div>@enderror
    </div>

    <div class="carte">
        <div class="carte-titre"><h3>Nouvelle assistante</h3></div>
        @if($peutAjouter)
            <form method="POST" action="{{ route('comptes.store') }}" class="carte-corps">
                @csrf
                <div class="champ">
                    <label for="name">Nom</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required>
                    @error('name')<div class="erreur">{{ $message }}</div>@enderror
                </div>
                <div class="champ">
                    <label for="email">E-mail de connexion</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required placeholder="awa@salon.local">
                    @error('email')<div class="erreur">{{ $message }}</div>@enderror
                </div>
                <div class="champ">
                    <label for="password_new">Mot de passe</label>
                    <input type="password" id="password_new" name="password" required minlength="6">
                </div>
                <div class="champ">
                    <label for="password_confirmation">Confirmer le mot de passe</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required minlength="6">
                </div>
                <button class="btn btn-primaire" type="submit">@include('partials.icone', ['nom' => 'plus']) Créer le compte</button>
            </form>
        @else
            <div class="carte-corps">
                @error('name')<div class="alerte alerte-erreur">{{ $message }}</div>@enderror
                <div class="alerte alerte-info" style="margin:0">
                    Limite atteinte : {{ $maximum }} comptes assistantes actifs au maximum.
                    Désactivez un compte pour en créer un nouveau.
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
