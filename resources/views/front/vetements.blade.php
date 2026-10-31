@extends('layouts.trame')

@section('title', 'Mes vêtements')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/vetements-trame.css') }}?v={{ filemtime(public_path('css/vetements-trame.css')) }}">
@endpush

@section('bandeau')
    <nav class="tr-ariane" aria-label="Fil d'Ariane">
        <ol>
            <li><a href="{{ route('front.home') }}">Accueil</a></li>
            <li><span aria-current="page">Mes vêtements</span></li>
        </ol>
    </nav>
    <div class="tr-bandeau__grille">
        <div class="tr-bandeau__principal">
            <h1>Mes vêtements</h1>
            <p class="tr-bandeau__intro">Suivez vos pièces en cours. Les réparations et dons validés passent dans l'historique.@if($analysePhotoDisponible) À la déclaration, la photo estime si une réparation a une deuxième chance.@endif</p>
        </div>
        <div class="tr-bandeau__cote">
            <a href="{{ route('front.historique') }}" class="tr-pilule">
                <i data-lucide="history" aria-hidden="true"></i> Historique
            </a>
            <button type="button" class="tr-pilule est-claire" data-open-modal="clothing-modal">
                <i data-lucide="plus" aria-hidden="true"></i> Déclarer un vêtement
            </button>
            @if($styleDisponible)
                <button type="button" class="tr-pilule" data-open-modal="restyle-modal">
                    <i data-lucide="sparkles" aria-hidden="true"></i> Restyle
                </button>
                <button type="button" class="tr-pilule" data-open-modal="style-modal">
                    <i data-lucide="box" aria-hidden="true"></i> Style 3D
                </button>
            @endif
        </div>
    </div>
@endsection

@section('content')
<main class="container page-content vetements-page">
    @if($vetements->isNotEmpty())
    @php($suggestionIa = $vetements->first(fn ($v) => ($v->analyseIa()['verdict'] ?? null) === 'reparable'))
    @php($vetementAReparer = $suggestionIa ?? $vetements->first(fn ($v) => $v->intended_action === 'reparation'))
    <div class="ai-banner">
        <i data-lucide="sparkles"></i>
        <div>
            <strong>{{ $suggestionIa ? 'Deuxième chance détectée' : 'Suggestion intelligente' }}</strong>
            @if($suggestionIa)
                <p>{{ $suggestionIa->analyseIa()['resume'] ?? '' }}</p>
            @elseif($vetementAReparer)
                <p>Trouvez un atelier pour réparer « {{ $vetementAReparer->displayName() }} ». Le rendez-vous sera prérempli.</p>
            @else
                <p>Consultez les ateliers partenaires pour faire réparer vos pièces.</p>
            @endif
        </div>
        <a href="{{ $suggestionIa ? route('front.ateliers', ['vetement_id' => (string) $suggestionIa->getKey()]) : ($vetementAReparer?->nextStepUrl() ?? route('front.ateliers')) }}" class="link-button">Trouver un atelier <i data-lucide="arrow-right"></i></a>
    </div>
    @endif

    <div class="clothes-grid">
        @forelse($vetements as $vetement)
            @php($done = $vetement->timelineProgress())
            <article class="clothing-card">
                <div class="clothing-image">
                    <img src="{{ $vetement->photoUrl() }}" alt="{{ $vetement->displayName() }}">
                    <button type="button" class="more-btn" aria-label="Actions"><i data-lucide="more-horizontal"></i></button>
                </div>
                <div class="clothing-info">
                    <div class="card-top">
                        <div>
                            <h3>{{ $vetement->displayName() }}</h3>
                            <p>{{ $vetement->type }} · Taille {{ $vetement->size }}</p>
                        </div>
                        <x-status-badge :tone="$vetement->statusTone()">{{ $vetement->statusLabel() }}</x-status-badge>
                    </div>
                    @if($vetement->analyseIa())
                        <div class="analyse-ia analyse-ia--{{ $vetement->analyseIa()['verdict'] ?? 'hors_sujet' }}">
                            <div class="analyse-ia-head">
                                <i data-lucide="sparkles"></i>
                                <strong>{{ $vetement->libelleVerdictAnalyse() }}</strong>
                            </div>
                            <p>{{ $vetement->analyseIa()['resume'] ?? '' }}</p>
                        </div>
                    @endif
                    <div class="condition"><span>État</span><strong>{{ $vetement->condition_label ?? '—' }}</strong></div>
                    <div class="condition" style="margin-top:6px"><span>Matière</span><strong>{{ $vetement->material ?? '—' }}</strong></div>
                    <div class="condition" style="margin-top:8px">
                        <span>Parcours</span>
                        <x-status-badge :tone="$vetement->intendedActionTone()">{{ $vetement->intendedActionLabel() }}</x-status-badge>
                    </div>
                    <div class="mini-timeline">
                        @for($i = 1; $i <= 4; $i++)
                            <span @class(['done' => $i <= $done, 'current' => $i === $done + 1 && $done < 4])></span>
                        @endfor
                    </div>
                    <div class="timeline-labels">
                        <span>{{ $vetement->cycleEvents->first()?->title ?? 'Déclaré' }}</span>
                        <span>{{ $vetement->cycleEvents->last()?->title ?? $vetement->statusLabel() }}</span>
                    </div>
                    <div class="card-actions" style="margin-top:14px;display:flex;flex-wrap:wrap;gap:8px;align-items:center">
                        @unless($vetement->estDansHistorique())
                        <form method="post" action="{{ route('front.vetements.action', $vetement->getKey()) }}" style="display:flex;gap:6px;flex-wrap:wrap">
                            @csrf
                            @method('PATCH')
                            <button type="submit" name="intended_action" value="reparation" class="btn btn-secondary small @if($vetement->intended_action === 'reparation') active-choice @endif">
                                <i data-lucide="wrench"></i> Réparation
                            </button>
                            <button type="submit" name="intended_action" value="don" class="btn btn-secondary small @if($vetement->intended_action === 'don') active-choice @endif">
                                <i data-lucide="gift"></i> Don
                            </button>
                        </form>
                        @if($vetement->nextStepUrl())
                            <a href="{{ $vetement->nextStepUrl() }}" class="btn btn-primary small">{{ $vetement->nextStepLabel() }}</a>
                        @endif
                        @if($analysePhotoDisponible && $vetement->image_path)
                            <form method="post" action="{{ route('front.vetements.analyser.piece', $vetement->getKey()) }}">
                                @csrf
                                <button type="submit" class="btn btn-secondary small">
                                    <i data-lucide="sparkles"></i> {{ $vetement->analyseIa() ? 'Relancer l\'analyse' : 'Analyser l\'état' }}
                                </button>
                            </form>
                        @endif
                        @if($styleDisponible && $vetement->image_path)
                            @php($analysePiece = $vetement->analyseIa())
                            <button type="button" class="btn btn-secondary small"
                                    data-restyle-piece="{{ route('front.vetements.restyle.piece', $vetement->getKey()) }}"
                                    data-restyle-photo="{{ $vetement->photoUrl() }}"
                                    @if(! empty($analysePiece['resume'])) data-restyle-instruction="{{ $analysePiece['resume'] }}" @endif>
                                <i data-lucide="sparkles"></i> Restyle
                            </button>
                            <button type="button" class="btn btn-secondary small" data-style-piece="{{ route('front.vetements.style.piece', $vetement->getKey()) }}">
                                <i data-lucide="box"></i> Style 3D
                            </button>
                        @endif
                        @else
                            <a href="{{ route('front.historique') }}" class="btn btn-secondary small"><i data-lucide="history"></i> Voir l'historique</a>
                        @endunless
                    </div>
                </div>
            </article>
        @empty
            <section class="tr-fil vetements-vide" aria-labelledby="vetements-vide">
                <h2 id="vetements-vide">Aucun vêtement en cours</h2>
                <p class="muted">Les pièces réparées ou données se retrouvent dans <a href="{{ route('front.historique') }}">l'historique du cycle de vie</a>.</p>
            </section>
        @endforelse
    </div>
</main>
@endsection

@push('overlays')
    <x-clothing-modal :disponible="$analysePhotoDisponible" />
    <x-clothing-chat :disponible="$chatDisponible" />
    <x-restyle-modal :disponible="$styleDisponible" />
    <x-style-modal :disponible="$styleDisponible" />
@endpush

@if($errors->any())
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const modal = document.getElementById('clothing-modal');
                if (modal) {
                    modal.classList.add('open');
                    modal.setAttribute('aria-hidden', 'false');
                }
            });
        </script>
    @endpush
@endif
