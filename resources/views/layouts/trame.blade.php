@extends('layouts.app')

{{--
    Gabarit minimaliste « trame », dérivé de l'accueil : barre de navigation et bandeau sombres, contenu clair.
    Pour y passer une page publique : @extends('layouts.trame'), puis
      @section('bandeau') fil d'Ariane, <h1>, intro ou actions (classes tr-bandeau__*) @endsection
      @section('content') le contenu de la page @endsection
--}}

{{-- viewport-fit=cover : nécessaire pour que env(safe-area-inset-*) ait une valeur (encoches des téléphones). --}}
@section('viewport', 'width=device-width, initial-scale=1, viewport-fit=cover')

{{-- @prepend : trame.css passe avant les styles de la page, qui peuvent ainsi l'affiner. --}}
@prepend('styles')
    <link rel="stylesheet" href="{{ asset('css/trame.css') }}">
@endprepend

@push('scripts')
    <script src="{{ asset('js/trame.js') }}"></script>
@endpush

@section('body')
<div class="front-shell tr-page">
    {{-- L'accueil apporte son hero plein écran (vidéo + barre). Les autres pages ont le bandeau sombre. --}}
    @hasSection('hero')
        @yield('hero')
        @include('layouts.partials.trame-messages')
        @yield('content')
        @include('layouts.partials.front-pied', ['trame' => true])
    @else
        @include('layouts.partials.barre-trame')

        <div class="tr-bandeau" id="contenu" tabindex="-1">
            <div class="tr-grille" aria-hidden="true"></div>
            @yield('bandeau')
        </div>

        <div class="tr-contenu">
            <div class="tr-grille" aria-hidden="true"></div>
            @include('layouts.partials.trame-messages')
            @yield('content')
        </div>

        @include('layouts.partials.front-pied', ['trame' => true])
    @endif

    {{-- Au-dessus de la barre fixe : les fenêtres ne restent pas coincées sous la navigation. --}}
    @stack('overlays')
</div>
@endsection
