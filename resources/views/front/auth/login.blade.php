@extends('layouts.trame')

@section('title', 'Connexion')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/client-trame.css') }}">
@endpush

@section('bandeau')
    <nav class="tr-ariane" aria-label="Fil d'Ariane">
        <ol>
            <li><a href="{{ route('front.home') }}">Accueil</a></li>
            <li><span aria-current="page">Connexion</span></li>
        </ol>
    </nav>
    <div class="tr-bandeau__grille">
        <div class="tr-bandeau__principal">
            <h1>Connexion</h1>
            <p class="tr-bandeau__intro">Accédez à vos vêtements, rendez-vous et dons. Les comptes atelier et association sont activés après validation administrative.</p>
        </div>
        <div class="tr-bandeau__cote">
            <a href="{{ route('front.register') }}" class="tr-pilule est-claire">
                <i data-lucide="user-plus" aria-hidden="true"></i> Créer un compte
            </a>
        </div>
    </div>
@endsection

@section('content')
<main class="auth-page container client-page">
    <div class="auth-card">
        <form action="{{ route('front.login.store') }}" method="post">
            @csrf
            <div class="field">
                <label for="email">Adresse e-mail</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="vous@exemple.tn" required autocomplete="email" @error('email') aria-invalid="true" aria-describedby="erreur-email" @enderror>
                @error('email')<p class="form-error" id="erreur-email">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password" @error('password') aria-invalid="true" aria-describedby="erreur-password" @enderror>
                @error('password')<p class="form-error" id="erreur-password">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Se souvenir de moi</label>
            </div>
            <div class="actions">
                <button type="submit" class="btn btn-primary full">Se connecter</button>
            </div>
            <p class="muted-link">Pas encore de compte ? <a href="{{ route('front.register') }}">Créer un compte</a></p>
        </form>
    </div>
</main>
@endsection
