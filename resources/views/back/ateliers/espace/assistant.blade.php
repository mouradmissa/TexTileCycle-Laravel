@extends('layouts.back')

@section('page-title', $pageTitle)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/ateliers-back.css') }}">
    <link rel="stylesheet" href="{{ asset('css/assistant-atelier.css') }}">
@endpush
@push('scripts')
    <script src="{{ asset('js/assistant-atelier.js') }}" defer></script>
@endpush

@section('content')
<div class="ab-page">
    @include('back.ateliers.espace.partials.entete', ['onglet' => 'assistant'])

    <section class="panel aa-page" aria-labelledby="aa-page-titre">
        <h2 id="aa-page-titre"><i data-lucide="sparkles" aria-hidden="true"></i> Assistant de mon atelier</h2>
        <p class="ab-muted">Posez une question sur vos rendez-vous, garanties, services ou avis : la réponse est calculée à partir de vos données.</p>
        @include('back.ateliers.espace.partials.assistant-panneau', ['question' => $question, 'reponse' => $reponse, 'idPrefixe' => 'aa-page'])
    </section>
</div>
@endsection
