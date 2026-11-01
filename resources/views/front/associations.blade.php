@extends('layouts.trame')

@section('title', 'Associations partenaires')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/client-trame.css') }}">
@endpush

@section('bandeau')
    <nav class="tr-ariane" aria-label="Fil d'Ariane">
        <ol>
            <li><a href="{{ route('front.home') }}">Accueil</a></li>
            <li><span aria-current="page">Associations</span></li>
        </ol>
    </nav>
    <div class="tr-bandeau__grille">
        <div class="tr-bandeau__principal">
            <h1>Associations partenaires</h1>
            <p class="tr-bandeau__intro">Des besoins réels, des dons qui comptent vraiment.</p>
        </div>
    </div>
@endsection

@section('content')
<main class="container page-content client-page">

    @if($associations->isEmpty())
        <div class="empty-state">
            <i data-lucide="heart-handshake"></i>
            <h3>Aucune association active</h3>
            <p>Les associations partenaires apparaîtront ici dès leur validation.</p>
        </div>
    @else
        <div class="association-grid">
            @foreach($associations as $asso)
                <article class="association-card">
                    <div class="association-head">
                        <div class="association-logo">
                            <i data-lucide="heart-handshake"></i>
                        </div>
                        <x-status-badge tone="purple">{{ count($asso->besoins ?? []) }} besoin(s)</x-status-badge>
                    </div>
                    <h3>{{ $asso->nom }}</h3>
                    <p class="muted small">{{ Str::limit($asso->description, 100) }}</p>

                    @if(!empty($asso->besoins))
                        <div class="besoins-tags">
                            @foreach(array_slice($asso->besoins, 0, 3) as $besoin)
                                <span class="tag">
                                    {{ $besoin['type'] ?? '' }}
                                    @if(!empty($besoin['taille'])) · {{ $besoin['taille'] }}@endif
                                    @if(!empty($besoin['quantite'])) ({{ $besoin['quantite'] }})@endif
                                </span>
                            @endforeach
                        </div>
                    @else
                        <p class="muted small">Aucun besoin spécifique renseigné.</p>
                    @endif

                    <div class="association-meta">
                        <span><i data-lucide="map-pin"></i> {{ $asso->adresse }}</span>
                        @if($asso->telephone)
                            <span><i data-lucide="phone"></i> {{ $asso->telephone }}</span>
                        @endif
                    </div>

                    @auth
                        <a href="{{ route('front.dons') }}?association={{ $asso->_id }}"
                           class="btn btn-secondary full">
                            Proposer un don <i data-lucide="arrow-right"></i>
                        </a>
                    @else
                        <a href="{{ route('front.login') }}" class="btn btn-secondary full">
                            Connexion pour donner <i data-lucide="arrow-right"></i>
                        </a>
                    @endauth
                </article>
            @endforeach
        </div>
    @endif
</main>
@endsection
