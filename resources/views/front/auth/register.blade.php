@extends('layouts.trame')

@section('title', 'Inscription')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/client-trame.css') }}">
@endpush

@section('bandeau')
    <nav class="tr-ariane" aria-label="Fil d'Ariane">
        <ol>
            <li><a href="{{ route('front.home') }}">Accueil</a></li>
            <li><span aria-current="page">Inscription</span></li>
        </ol>
    </nav>
    <div class="tr-bandeau__grille">
        <div class="tr-bandeau__principal">
            <h1>Inscription</h1>
            <p class="tr-bandeau__intro">Choisissez le type de compte. Les ateliers et associations sont activés après validation administrative.</p>
        </div>
        <div class="tr-bandeau__cote">
            <a href="{{ route('front.login') }}" class="tr-pilule est-claire">
                <i data-lucide="log-in" aria-hidden="true"></i> Se connecter
            </a>
        </div>
    </div>
@endsection

@section('content')
<main class="auth-page container client-page">
    <div class="auth-card">
        <form action="{{ route('front.register.store') }}" method="post">
            @csrf
            <div class="field">
                <label for="name">Nom complet</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="Yasmine Ben Ali" required autocomplete="name" @error('name') aria-invalid="true" aria-describedby="erreur-name" @enderror>
                @error('name')<p class="form-error" id="erreur-name">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="email">Adresse e-mail</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="vous@exemple.tn" required autocomplete="email" @error('email') aria-invalid="true" aria-describedby="erreur-email" @enderror>
                @error('email')<p class="form-error" id="erreur-email">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required minlength="8" autocomplete="new-password" @error('password') aria-invalid="true" aria-describedby="erreur-password" @enderror>
                @error('password')<p class="form-error" id="erreur-password">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="password_confirmation">Confirmer le mot de passe</label>
                <input type="password" id="password_confirmation" name="password_confirmation" placeholder="••••••••" required minlength="8" autocomplete="new-password" @error('password_confirmation') aria-invalid="true" aria-describedby="erreur-password-confirmation" @enderror>
                @error('password_confirmation')<p class="form-error" id="erreur-password-confirmation">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="role">Type de compte</label>
                <select id="role" name="role" required @error('role') aria-invalid="true" aria-describedby="erreur-role" @enderror>
                    <option value="citoyen" @selected(old('role', 'citoyen') === 'citoyen')>Citoyen — dépôt, réparation, dons</option>
                    <option value="atelier" @selected(old('role') === 'atelier')>Atelier — réparation (validation admin)</option>
                    <option value="association" @selected(old('role') === 'association')>Association — dons (validation admin)</option>
                </select>
                <p class="muted small" style="margin-top:8px">Le rôle administrateur n'est pas disponible à l'inscription.</p>
                @error('role')<p class="form-error" id="erreur-role">{{ $message }}</p>@enderror
            </div>
            <div class="field field-telephone" id="champ-telephone" @if(old('role', 'citoyen') !== 'citoyen') hidden @endif>
                <label for="phone">Numéro de téléphone</label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" placeholder="+216 22 123 456" inputmode="tel" autocomplete="tel" @if(old('role', 'citoyen') === 'citoyen') required @endif @error('phone') aria-invalid="true" aria-describedby="erreur-phone" @enderror>
                <p class="muted small" id="aide-phone" style="margin-top:8px">Demandé uniquement pour un compte client.</p>
                @error('phone')<p class="form-error" id="erreur-phone">{{ $message }}</p>@enderror
            </div>
            <div class="actions">
                <button type="submit" class="btn btn-primary full">Créer mon compte</button>
            </div>
            <p class="muted-link">Déjà inscrit ? <a href="{{ route('front.login') }}">Se connecter</a></p>
        </form>
    </div>
</main>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const role = document.getElementById('role');
        const bloc = document.getElementById('champ-telephone');
        const telephone = document.getElementById('phone');
        if (!role || !bloc || !telephone) return;

        const afficher = () => {
            const client = role.value === 'citoyen';
            bloc.hidden = !client;
            telephone.required = client;
            if (!client) telephone.value = '';
        };

        role.addEventListener('change', afficher);
        afficher();
    });
</script>
@endpush
