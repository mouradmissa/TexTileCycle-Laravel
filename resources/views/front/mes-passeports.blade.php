@extends('layouts.trame')

@section('title', 'Mes passeports de réparation')

@push('styles')
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="{{ asset('css/passeport.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/passeport.js') }}" defer></script>
@endpush

@section('bandeau')
    <nav class="tr-ariane" aria-label="Fil d'Ariane">
        <ol>
            <li><a href="{{ route('front.home') }}">Accueil</a></li>
            <li><span aria-current="page">Mes passeports</span></li>
        </ol>
    </nav>
    <div class="tr-bandeau__grille">
        <div class="tr-bandeau__principal">
            <h1>Mes passeports de réparation</h1>
            <p class="tr-bandeau__intro">Chaque pièce réparée par un atelier partenaire reçoit un passeport : montrez son QR code
               quand vous la revendez ou la donnez, il prouve qu'elle a été réparée par un professionnel.</p>
        </div>
    </div>
@endsection

@section('content')
<main class="container page-content pp">
    @if ($passeports->isEmpty())
        <section class="tr-fil" aria-labelledby="mp-vide">
            <h2 id="mp-vide">Aucun passeport pour l'instant</h2>
            <p class="muted">Après une réparation terminée, l'atelier génère le passeport de votre pièce : il apparaîtra ici.</p>
            <p><a href="{{ route('front.ateliers') }}" class="btn btn-primary">
                <i data-lucide="search" aria-hidden="true"></i> Trouver un atelier
            </a></p>
        </section>
    @else
        @php
            $actifs = $passeports->reject(fn ($p) => $p->estRevoque());
            $sousGarantie = $actifs->filter(fn ($p) => $p->garantieActive())->count();
        @endphp
        <p class="mp-resume">
            <strong>{{ $actifs->count() }}</strong> pièce{{ $actifs->count() > 1 ? 's' : '' }} certifiée{{ $actifs->count() > 1 ? 's' : '' }}
            @if ($sousGarantie > 0)
                · <strong>{{ $sousGarantie }}</strong> encore sous garantie
            @endif
        </p>

        <ul class="mp-liste">
            @foreach ($passeports as $passeport)
                @php $lien = route('front.passeport', ['code' => $passeport->code]); @endphp
                <li @class(['mp-carte', 'is-revoque' => $passeport->estRevoque()]) data-pp-bloc>
                    <img class="mp-carte__qr" src="{{ route('front.passeport.qr', ['code' => $passeport->code]) }}" alt="QR code du passeport {{ $passeport->numeroCourt() }}" width="88" height="88" loading="lazy">
                    <div class="mp-carte__corps">
                        <p class="mp-carte__numero">n° {{ $passeport->numeroCourt() }}</p>
                        <h2>{{ $passeport->vetementAffiche() }}</h2>
                        <p>{{ $passeport->reparationAffichee() }} · {{ $passeport->info('atelier') }}</p>
                        <p>Réparé le {{ $passeport->dateReparationAffichee() }}</p>
                        @if ($passeport->estRevoque())
                            <x-status-badge tone="red">Révoqué</x-status-badge>
                        @elseif ($passeport->garantieActive())
                            <p class="pp-frise__garantie is-active">
                                <i data-lucide="shield-check" aria-hidden="true"></i>
                                Garantie jusqu'au {{ $passeport->garantieFin()->locale('fr')->isoFormat('D MMM YYYY') }}
                            </p>
                        @elseif ($passeport->garantieJours() > 0)
                            <p class="pp-frise__garantie">
                                <i data-lucide="shield-off" aria-hidden="true"></i> Garantie expirée
                            </p>
                        @endif
                        <div class="mp-carte__liens">
                            <a href="{{ $lien }}">Voir le passeport</a>
                            <a href="{{ route('front.passeport.qr', ['code' => $passeport->code]) }}" download="passeport-{{ $passeport->numeroCourt() }}.svg">Télécharger le QR</a>
                        </div>
                        @unless ($passeport->estRevoque())
                            <div class="mp-carte__actions" data-pp-actions data-pp-url="{{ $lien }}" hidden>
                                <button type="button" class="btn btn-secondary small" data-pp-copier>
                                    <i data-lucide="copy" aria-hidden="true"></i> Copier le lien
                                    <span class="tr-sr">du passeport {{ $passeport->numeroCourt() }}</span>
                                </button>
                                <button type="button" class="btn btn-secondary small" data-pp-partager hidden>
                                    <i data-lucide="share-2" aria-hidden="true"></i> Partager
                                </button>
                            </div>
                            <p class="pp-actions__statut" role="status" data-pp-statut></p>
                        @endunless
                    </div>
                </li>
            @endforeach
        </ul>
        <p class="mp-verifier"><a href="{{ route('front.passeport.verifier') }}">Vérifier le passeport d'une autre pièce</a></p>
    @endif
</main>
@endsection
