@extends('layouts.trame')

@section('title', 'Modifier le rendez-vous')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/client-trame.css') }}">
@endpush

@section('bandeau')
    <nav class="tr-ariane" aria-label="Fil d'Ariane">
        <ol>
            <li><a href="{{ route('front.home') }}">Accueil</a></li>
            <li><a href="{{ route('front.rdv') }}">Rendez-vous</a></li>
            <li><span aria-current="page">Modifier</span></li>
        </ol>
    </nav>
    <div class="tr-bandeau__grille">
        <div class="tr-bandeau__principal">
            <h1>Modifier le rendez-vous</h1>
            <p class="tr-bandeau__intro">Vous pouvez modifier les détails tant que le rendez-vous est en attente.</p>
        </div>
    </div>
@endsection

@section('content')
<main class="container page-content client-page">

    {{-- Formulaire de modification (même design, pré-rempli) --}}
    <div class="panel" style="padding:24px;max-width:720px">
        @include('rendez-vous._form', ['rendezVous' => $rendezVous])
    </div>
</main>
@endsection
