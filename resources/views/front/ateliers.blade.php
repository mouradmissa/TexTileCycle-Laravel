@extends('layouts.trame')

@section('title', 'Ateliers de réparation')

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <link rel="stylesheet" href="{{ asset('css/ateliers.css') }}">
    <link rel="stylesheet" href="{{ asset('css/ateliers-trame.css') }}">
@endpush

@section('bandeau')
    <nav class="tr-ariane" aria-label="Fil d'Ariane">
        <ol>
            <li><a href="{{ route('front.home') }}">Accueil</a></li>
            <li><span aria-current="page">Ateliers</span></li>
        </ol>
    </nav>
    <div class="tr-bandeau__grille">
        <div class="tr-bandeau__principal">
            <h1 id="ateliers-titre">Ateliers près de chez vous</h1>
            <p class="tr-bandeau__intro">Réparer, retoucher, transformer : des ateliers partenaires pour prolonger la vie de vos vêtements.</p>
        </div>
        <div class="tr-bandeau__cote">
            <a href="{{ route('front.ateliers.diagnostic') }}" class="tr-pilule est-claire">
                <i data-lucide="camera" aria-hidden="true"></i> Diagnostiquer mon vêtement par photo
            </a>
            <a href="{{ route('front.ateliers.assistant') }}" class="tr-pilule">
                <i data-lucide="message-circle-question" aria-hidden="true"></i> Poser une question à l'assistant
            </a>
        </div>
    </div>
@endsection

@section('content')
{{-- Les zones data-swap restent dans [data-ateliers-page] : ateliers-map.js les remplace à chaque recherche. --}}
<main class="ateliers-page" data-ateliers-page>
    <section class="ateliers-hero" aria-label="Rechercher un atelier">
        <div class="container">
            <p class="ateliers-hero__subtitle" data-swap="total" aria-live="polite">
                @if ($total === 0)
                    Aucun atelier ne correspond à votre recherche pour le moment.
                @else
                    <strong>{{ $total }}</strong> {{ $total > 1 ? 'ateliers partenaires' : 'atelier partenaire' }} pour réparer, retoucher et transformer vos vêtements.
                @endif
            </p>

            @if ($vetementPourRdv ?? null)
                <div class="ai-banner">
                    <i data-lucide="shirt"></i>
                    <div>
                        <strong>Réparation de « {{ $vetementPourRdv->displayName() }} »</strong>
                        <p>Choisissez un atelier : le formulaire de rendez-vous sera déjà rempli avec ce vêtement et l'atelier.</p>
                    </div>
                </div>
            @endif

            <form class="ateliers-search" method="get" action="{{ route('front.ateliers') }}" role="search" aria-label="Rechercher un atelier" data-filters-form>
                @if (! empty($listQuery['vetement_id']))
                    <input type="hidden" name="vetement_id" value="{{ $listQuery['vetement_id'] }}">
                @endif
                <div class="ateliers-search__main">
                    <label for="atelier-q" class="sr-only">Rechercher par nom d'atelier, ville, spécialité ou service</label>
                    <div class="ateliers-search__field">
                        <i data-lucide="search" aria-hidden="true"></i>
                        <input id="atelier-q" type="search" name="q" value="{{ old('q', $filters['q'] ?? '') }}" placeholder="Retouche denim, ourlet, La Marsa…" autocomplete="off">
                    </div>
                    <button type="submit" class="btn btn-primary">Rechercher</button>
                </div>

                <div class="ateliers-filters">
                    <div class="filter-field">
                        <label for="filtre-service" class="filter-field__label">Service</label>
                        <select id="filtre-service" name="service" data-auto-submit>
                            <option value="">Tous les services</option>
                            @foreach ($serviceNames as $nom)
                                <option value="{{ $nom }}" @selected(($filters['service'] ?? null) === $nom)>{{ $nom }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-field">
                        <label for="filtre-ville" class="filter-field__label">Ville</label>
                        <select id="filtre-ville" name="ville" data-auto-submit>
                            <option value="">Toutes les villes</option>
                            @foreach ($villes as $ville)
                                <option value="{{ $ville }}" @selected(($filters['ville'] ?? null) === $ville)>{{ $ville }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-field">
                        <label for="filtre-note" class="filter-field__label">Note minimale</label>
                        <select id="filtre-note" name="note_min" data-auto-submit>
                            <option value="">Toutes les notes</option>
                            @foreach ($notesMin as $note)
                                <option value="{{ $note }}" @selected(isset($filters['note_min']) && (float) $filters['note_min'] === (float) $note)>{{ str_replace('.', ',', $note) }} et plus</option>
                            @endforeach
                        </select>
                    </div>

                    <div @class(['filter-field', 'is-disabled' => ! $hasPosition]) data-rayon-field>
                        <label for="filtre-rayon" class="filter-field__label">Rayon</label>
                        <select id="filtre-rayon" name="rayon_km" data-auto-submit @disabled(! $hasPosition) aria-describedby="filtre-rayon-aide">
                            <option value="">Sans limite</option>
                            @foreach ($rayons as $rayon)
                                <option value="{{ $rayon }}" @selected(isset($filters['rayon_km']) && (float) $filters['rayon_km'] === (float) $rayon)>{{ $rayon }} km</option>
                            @endforeach
                        </select>
                        <span id="filtre-rayon-aide" class="sr-only">Disponible après avoir partagé votre position avec « Me localiser ».</span>
                    </div>

                    <div class="filter-field">
                        <label for="filtre-tri" class="filter-field__label">Trier par</label>
                        <select id="filtre-tri" name="tri" data-auto-submit>
                            <option value="pertinence" @selected(($filters['tri'] ?? 'pertinence') === 'pertinence')>Mieux notés</option>
                            <option value="distance" @selected(($filters['tri'] ?? null) === 'distance') @disabled(! $hasPosition) data-tri-distance>Plus proches</option>
                            <option value="nom" @selected(($filters['tri'] ?? null) === 'nom')>Nom (A → Z)</option>
                        </select>
                    </div>

                    <input type="hidden" name="lat" value="{{ $filters['lat'] ?? '' }}" @disabled(! $hasPosition) data-position-lat>
                    <input type="hidden" name="lng" value="{{ $filters['lng'] ?? '' }}" @disabled(! $hasPosition) data-position-lng>

                    <div class="ateliers-filters__actions">
                        <button type="button" @class(['btn', 'btn-secondary', 'locate-btn', 'is-located' => $hasPosition]) data-locate>
                            <i data-lucide="{{ $hasPosition ? 'locate-fixed' : 'locate' }}" aria-hidden="true"></i>
                            <span data-locate-label>{{ $hasPosition ? 'Position utilisée' : 'Me localiser' }}</span>
                        </button>
                        <a href="{{ route('front.ateliers', array_filter(['vetement_id' => $listQuery['vetement_id'] ?? null])) }}" class="btn btn-secondary reset-btn" data-ajax-link>
                            <i data-lucide="rotate-ccw" aria-hidden="true"></i> Réinitialiser
                        </a>
                    </div>
                </div>

                <p class="ateliers-filters__status" data-locate-status role="status" aria-live="polite"></p>
            </form>

            {{-- Erreurs de SearchAteliersRequest. Zone remplacée à chaque recherche : elle disparaît dès qu'une recherche est valide. --}}
            <div data-swap="erreurs">
                @if ($errors->any())
                    <div class="ateliers-search__errors" role="alert">
                        <i data-lucide="alert-circle" aria-hidden="true"></i>
                        <ul>
                            @foreach ($errors->all() as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div data-swap="chips">
                @if (count($activeFilters) > 0)
                    <ul class="active-filters" aria-label="Filtres actifs">
                        @foreach ($activeFilters as $puce)
                            <li>
                                <a href="{{ $puce['url'] }}" class="filter-chip" data-ajax-link aria-label="Retirer le filtre {{ $puce['label'] }}">
                                    {{ $puce['label'] }} <i data-lucide="x" aria-hidden="true"></i>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </section>

    <div class="container ateliers-body">
        <div class="view-switch" role="group" aria-label="Mode d'affichage" data-view-switch hidden>
            <button type="button" class="view-switch__btn" aria-pressed="true" aria-controls="ateliers-liste" data-view-btn="list">
                <i data-lucide="list" aria-hidden="true"></i> Liste
            </button>
            <button type="button" class="view-switch__btn" aria-pressed="false" aria-controls="ateliers-carte" data-view-btn="map">
                <i data-lucide="map" aria-hidden="true"></i> Carte
            </button>
        </div>

        <div class="ateliers-layout" data-view="list" data-layout>
            <section id="ateliers-liste" class="ateliers-results tr-fil" aria-label="Liste des ateliers" data-swap="results">
                @forelse ($ateliers as $atelier)
                    @include('front.partials.atelier-card', ['atelier' => $atelier, 'listQuery' => $listQuery])
                @empty
                    <div class="ateliers-empty">
                        <div class="ateliers-empty__icon" aria-hidden="true"><i data-lucide="search-x"></i></div>
                        <h2>Aucun atelier trouvé</h2>
                        <p>Essayez un autre mot-clé, élargissez le rayon ou retirez un filtre.</p>
                        <a href="{{ route('front.ateliers', array_filter(['vetement_id' => $listQuery['vetement_id'] ?? null])) }}" class="btn btn-primary" data-ajax-link>
                            <i data-lucide="rotate-ccw" aria-hidden="true"></i> Réinitialiser les filtres
                        </a>
                    </div>
                @endforelse

                {{ $ateliers->links('vendor.pagination.textilecycle') }}
            </section>

            <aside id="ateliers-carte" class="ateliers-map-panel tr-fil" aria-label="Carte des ateliers">
                <x-atelier-map
                    :markers="$markers"
                    mode="list"
                    label="Carte interactive des ateliers trouvés"
                    :endpoint="route('front.ateliers.map')"
                    :user-lat="$hasPosition ? $filters['lat'] : null"
                    :user-lng="$hasPosition ? $filters['lng'] : null"
                    alternative="La liste des ateliers, à côté de la carte, présente les mêmes résultats avec leurs adresses."
                />
            </aside>
        </div>
    </div>
</main>
@endsection

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="{{ asset('js/ateliers-map.js') }}"></script>
@endpush
