@extends('layouts.trame')

@section('title', 'Prendre rendez-vous')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/client-trame.css') }}">
@endpush

@php
    $atelierNom = $ateliers[$atelierPreselect ?? ''] ?? null;
    $vetementNom = null;
    if (! empty($vetementPreselect)) {
        $vetementNom = optional($vetements->first(fn ($v) => (string) $v->getKey() === (string) $vetementPreselect))->displayName();
    }
@endphp

@section('bandeau')
    <nav class="tr-ariane" aria-label="Fil d'Ariane">
        <ol>
            <li><a href="{{ route('front.home') }}">Accueil</a></li>
            <li><a href="{{ route('front.rdv') }}">Rendez-vous</a></li>
            <li><span aria-current="page">Nouveau</span></li>
        </ol>
    </nav>
    <div class="tr-bandeau__grille">
        <div class="tr-bandeau__principal">
            <h1>Prendre rendez-vous</h1>
            @if($vetementNom || $atelierNom)
                <p class="tr-bandeau__intro">Complétez la date, la durée et un commentaire : le vêtement et l'atelier sont déjà renseignés.</p>
            @else
                <p class="tr-bandeau__intro">Choisissez votre vêtement, l'atelier et un créneau disponible.</p>
            @endif
        </div>
    </div>
@endsection

@section('content')
<main class="container page-content client-page">

    @if($vetementNom || $atelierNom)
        <div class="ai-banner">
            <i data-lucide="calendar-check"></i>
            <div>
                <strong>Formulaire prérempli</strong>
                <p>
                    @if($vetementNom) Vêtement : <b>{{ $vetementNom }}</b>@endif
                    @if($vetementNom && $atelierNom) — @endif
                    @if($atelierNom) Atelier : <b>{{ $atelierNom }}</b>@endif
                </p>
            </div>
        </div>
    @endif

    <div class="panel" style="padding:24px;max-width:720px">
        @include('rendez-vous._form')
    </div>
</main>

<x-assistant-rdv />
@endsection
