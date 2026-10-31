@extends('layouts.trame')

@section('title', 'Historique du cycle de vie')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/vetements-trame.css') }}">
@endpush

@section('bandeau')
    <nav class="tr-ariane" aria-label="Fil d'Ariane">
        <ol>
            <li><a href="{{ route('front.home') }}">Accueil</a></li>
            <li><a href="{{ route('front.vetements') }}">Mes vêtements</a></li>
            <li><span aria-current="page">Historique</span></li>
        </ol>
    </nav>
    <div class="tr-bandeau__grille">
        <div class="tr-bandeau__principal">
            <h1>Cycle de vie</h1>
            <p class="tr-bandeau__intro">Historique complet de vos vêtements après une réparation validée ou un don accepté.</p>
        </div>
        <div class="tr-bandeau__cote">
            <a href="{{ route('front.vetements') }}" class="tr-pilule est-claire">
                <i data-lucide="shirt" aria-hidden="true"></i> Mes vêtements
            </a>
        </div>
    </div>
@endsection

@section('content')
<main class="container page-content historique-page">
    <div class="history-filters" role="navigation" aria-label="Filtrer l'historique">
        @foreach($filtres as $valeur => $libelle)
            <a href="{{ $valeur === '' ? route('front.historique') : route('front.historique', ['statut' => $valeur]) }}"
               class="history-filter {{ ($filtre ?: '') === $valeur ? 'is-active' : '' }}"
               @if(($filtre ?: '') === $valeur) aria-current="page" @endif>
                {{ $libelle }}
            </a>
        @endforeach
    </div>

    <div class="history-list">
        @forelse($vetements as $vetement)
            <article class="history-card">
                <div class="history-card-head">
                    <div class="history-thumb">
                        <img src="{{ $vetement->photoUrl() }}" alt="{{ $vetement->displayName() }}">
                    </div>
                    <div class="history-meta">
                        <div class="card-top">
                            <div>
                                <h3>{{ $vetement->displayName() }}</h3>
                                <p>{{ $vetement->material ?? $vetement->type }} · Taille {{ $vetement->size }}</p>
                            </div>
                            <x-status-badge :tone="$vetement->statusTone()">{{ $vetement->statusLabel() }}</x-status-badge>
                        </div>
                        <p class="muted small">{{ $vetement->cycleEvents->count() }} étape{{ $vetement->cycleEvents->count() > 1 ? 's' : '' }} dans le parcours</p>
                    </div>
                </div>

                <ol class="cycle-timeline">
                    @forelse($vetement->cycleEvents as $event)
                        <li class="cycle-step {{ $event->step_key === 'termine' ? 'is-done' : '' }}">
                            <span class="cycle-dot" aria-hidden="true">
                                <i data-lucide="{{ $event->icon() }}"></i>
                            </span>
                            <div class="cycle-step-body">
                                <div class="cycle-step-top">
                                    <b>{{ $event->title }}</b>
                                    @if($event->occurredLabel())
                                        <time datetime="{{ $event->occurred_at?->toIso8601String() }}">{{ $event->occurredLabel() }}</time>
                                    @endif
                                </div>
                                @if($event->description)
                                    <p>{{ $event->description }}</p>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="cycle-step">
                            <span class="cycle-dot" aria-hidden="true"><i data-lucide="circle"></i></span>
                            <div class="cycle-step-body">
                                <b>{{ $vetement->statusLabel() }}</b>
                                <p>Aucun détail d'étape n'a encore été enregistré pour cette pièce.</p>
                            </div>
                        </li>
                    @endforelse
                </ol>
            </article>
        @empty
            <section class="tr-fil vetements-vide" aria-labelledby="historique-vide">
                <i data-lucide="history" aria-hidden="true"></i>
                <h2 id="historique-vide">Aucun cycle clôturé pour le moment</h2>
                <p class="muted">Dès qu'un atelier valide une réparation ou qu'une association accepte un don, la pièce apparaît ici avec tout son historique.</p>
                <p><a href="{{ route('front.vetements') }}" class="btn btn-primary"><i data-lucide="plus" aria-hidden="true"></i> Déclarer un vêtement</a></p>
            </section>
        @endforelse
    </div>
</main>
@endsection

@push('overlays')
    <x-clothing-chat :disponible="$chatDisponible" />
@endpush
