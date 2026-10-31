@extends('layouts.trame')

@section('title', 'Mon profil')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/client-trame.css') }}">
    <link rel="stylesheet" href="{{ asset('css/vetements-trame.css') }}">
@endpush

@section('bandeau')
    <nav class="tr-ariane" aria-label="Fil d'Ariane">
        <ol>
            <li><a href="{{ route('front.home') }}">Accueil</a></li>
            <li><span aria-current="page">Profil</span></li>
        </ol>
    </nav>
    <div class="tr-bandeau__grille">
        <div class="tr-bandeau__principal">
            <h1>Mon profil</h1>
            <p class="tr-bandeau__intro">Vos informations de compte client.</p>
        </div>
    </div>
@endsection

@section('content')
<main class="container client-page profil-page">
    <article class="profil-carte">
        <header class="profil-identite">
            <p class="profil-initiales" aria-hidden="true">{{ $user->initials() }}</p>
            <div>
                <p class="profil-kicker">Compte client</p>
                <h2>{{ $user->name }}</h2>
                <p>{{ $user->email }}</p>
            </div>
            <button type="button" class="profil-modifier" data-open-modal="profil-modal">
                Modifier mes informations
            </button>
        </header>

        <dl class="profil-liste">
            <div>
                <dt>Nom complet</dt>
                <dd>{{ $user->name }}</dd>
            </div>
            <div>
                <dt>Adresse e-mail</dt>
                <dd>{{ $user->email }}</dd>
            </div>
            <div>
                <dt>Numéro de téléphone</dt>
                <dd>{{ $user->phone ?: 'Non renseigné' }}</dd>
            </div>
            <div>
                <dt>Type de compte</dt>
                <dd>{{ $user->roleLabel() }}</dd>
            </div>
        </dl>
    </article>
</main>
@endsection

@push('overlays')
<div @class(['modal-backdrop', 'open' => $errors->any()]) id="profil-modal" aria-hidden="{{ $errors->any() ? 'false' : 'true' }}">
    <div class="modal modal-declarer" role="dialog" aria-modal="true" aria-labelledby="profil-modal-titre">
        <header class="decl-entete">
            <div>
                <p class="decl-kicker">Compte client</p>
                <h2 id="profil-modal-titre">Changer mes informations</h2>
            </div>
            <button type="button" class="decl-fermer" data-close-modal aria-label="Fermer">
                <i data-lucide="x" aria-hidden="true"></i>
            </button>
        </header>

        <form class="decl-form" action="{{ route('front.profil.update') }}" method="post">
            @csrf
            @method('PUT')
            @if($errors->any())
                <div class="decl-erreurs" role="alert">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <section class="decl-etape">
                <h3>1 · Coordonnées</h3>
                <p class="decl-aide">Ces informations remplacent celles affichées sur votre profil.</p>
                <label>Nom complet
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name">
                </label>
                <label>Adresse e-mail
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="email">
                </label>
                <label>Numéro de téléphone
                    <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="+216 22 123 456" required inputmode="tel" autocomplete="tel">
                </label>
            </section>

            <section class="decl-etape">
                <h3>2 · Mot de passe</h3>
                <p class="decl-aide">Laissez ces champs vides pour conserver le mot de passe actuel.</p>
                <label>Mot de passe actuel
                    <input type="password" name="current_password" autocomplete="current-password">
                </label>
                <label>Nouveau mot de passe
                    <input type="password" name="password" minlength="8" autocomplete="new-password">
                </label>
                <label>Confirmer le nouveau mot de passe
                    <input type="password" name="password_confirmation" minlength="8" autocomplete="new-password">
                </label>
            </section>

            <div class="modal-actions decl-actions">
                <button type="button" class="btn btn-secondary" data-close-modal>Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
@endpush
