@extends('layouts.trame')

@section('title', 'Accès refusé')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/client-trame.css') }}">
@endpush

@section('bandeau')
    <nav class="tr-ariane" aria-label="Fil d'Ariane">
        <ol>
            <li><a href="{{ route('front.home') }}">Accueil</a></li>
            <li><span aria-current="page">Accès refusé</span></li>
        </ol>
    </nav>
    <div class="tr-bandeau__grille">
        <div class="tr-bandeau__principal">
            <h1>Accès refusé</h1>
            <p class="tr-bandeau__intro">{{ $exception->getMessage() ?: 'Vous n\'avez pas les droits pour afficher cette page.' }}</p>
        </div>
    </div>
@endsection

@section('content')
<main class="container page-content client-page">
    <p>
        @auth
            @if(auth()->user()->canAccessBackOffice())
                <a href="{{ route('back.dashboard') }}" class="btn btn-primary">Retour au back-office</a>
            @else
                <a href="{{ route('front.home') }}" class="btn btn-primary">Retour à l'accueil</a>
            @endif
        @else
            <a href="{{ route('front.login') }}" class="btn btn-primary">Se connecter</a>
        @endauth
    </p>
</main>
@endsection
