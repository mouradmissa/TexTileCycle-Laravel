@extends('layouts.trame')

{{-- Carte : mêmes Leaflet, styles et script que la page Ateliers. accueil.css : propre à l'accueil.
     trame.css et trame.js viennent du gabarit. --}}
@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <link rel="stylesheet" href="{{ asset('css/ateliers.css') }}">
    <link rel="stylesheet" href="{{ asset('css/accueil.css') }}">
@endpush

{{-- Ordre important : ateliers-map.js crée la carte avant qu'accueil.js n'y place les ateliers. --}}
@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="{{ asset('js/ateliers-map.js') }}"></script>
    <script src="{{ asset('js/accueil.js') }}"></script>
@endpush

@section('hero')
@include('layouts.partials.barre-trame')

<section class="hv" id="contenu" tabindex="-1" aria-labelledby="hv-titre" data-hv>
    <div class="hv-media" aria-hidden="true">
        <div class="hv-cadre">
            <video class="hv-video" autoplay muted loop playsinline preload="auto" tabindex="-1"
                   poster="{{ asset('videos/accueil-hero-poster.jpg') }}" data-hv-video>
                <source src="{{ asset('videos/accueil-hero.mp4') }}" type="video/mp4">
            </video>
        </div>
    </div>
    <div class="hv-voile" aria-hidden="true"></div>
    <div class="tr-grille" aria-hidden="true"></div>

    <div class="hv-infos">
        <p class="hv-accroche">La plateforme tunisienne<br>de l'économie circulaire<br>textile.</p>
        <address class="hv-contact">
            <a href="mailto:hello@textilecycle.tn">hello@textilecycle.tn</a>
            <span>Tunis, Tunisie</span>
        </address>
    </div>

    <div class="hv-services">
        <ul>
            <li><a href="{{ route('front.vetements') }}"><span class="hv-num">01</span> Déclarer</a></li>
            <li><a href="{{ route('front.ateliers') }}"><span class="hv-num">02</span> Réparer</a></li>
            <li><a href="{{ route('front.associations') }}"><span class="hv-num">03</span> Donner</a></li>
        </ul>
        <span class="hv-trait" aria-hidden="true"></span>
    </div>

    <h1 class="hv-titre" id="hv-titre">
        <span class="tr-sr">Donnez une seconde vie à vos vêtements.</span>
        <span class="hv-ligne" aria-hidden="true"><span>Donnez une</span></span>
        <span class="hv-ligne" aria-hidden="true"><span>seconde vie</span></span>
        <span class="hv-ligne" aria-hidden="true"><span>à vos <em>vêtements.</em></span></span>
    </h1>

    <button type="button" class="hv-pause" data-hv-pause aria-label="Mettre la vidéo d'arrière-plan en pause">
        <span class="hv-pause__pause"><i data-lucide="pause" aria-hidden="true"></i> Pause</span>
        <span class="hv-pause__lecture" hidden><i data-lucide="play" aria-hidden="true"></i> Lecture</span>
    </button>
</section>
@endsection

@section('content')
{{-- Chaque .tr-rang (css/trame.css) est un « fil » horizontal ; un point marque chaque colonne de contenu. --}}
<main class="acc" data-acc>
    <div class="tr-grille" aria-hidden="true"></div>

    <section class="acc-section" id="chiffres" aria-labelledby="acc-chiffres-titre">
        <h2 class="tr-sr" id="acc-chiffres-titre">Notre impact</h2>
        <div class="tr-rang" data-tr-fil>
            <p class="acc-phrase s4">
                Ensemble, nous avons déjà sauvé <strong>1 240 vêtements</strong>, réalisé <strong>856 réparations</strong>,
                redistribué <strong>384 dons</strong> et évité <strong>2,3 t de CO₂</strong>.
            </p>
        </div>
    </section>

    <section class="acc-section" id="parcours" aria-labelledby="acc-parcours-titre">
        <div class="acc-entete">
            <h2 class="acc-titre" id="acc-parcours-titre">Chaque geste<br>compte.</h2>
            <p class="acc-intro">Une expérience simple pour transformer vos habitudes et mesurer votre impact.</p>
        </div>
        <ol class="tr-rang acc-etapes" data-tr-fil>
            @foreach([
                ['01', 'Déclarer', 'Dites-nous ce que vous avez dans votre armoire.'],
                ['02', 'Réparer ou donner', 'Choisissez la meilleure seconde vie.'],
                ['03', 'Suivre le parcours', 'Restez informé à chaque étape.'],
                ['04', 'Mesurer l\'impact', 'Visualisez votre contribution.'],
            ] as [$num, $title, $desc])
                <li>
                    <span class="hv-num" aria-hidden="true">{{ $num }}</span>
                    <h3>{{ $title }}</h3>
                    <p>{{ $desc }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="acc-section" aria-labelledby="acc-reseau-titre">
        <div class="tr-rang" data-tr-fil>
            <div class="acc-reseau s2">
                <h2 class="acc-titre est-moyen" id="acc-reseau-titre">Près de chez vous,<br>un impact réel.</h2>
                <p class="acc-intro">Découvrez les ateliers et associations qui font bouger la mode en Tunisie.</p>
                <a href="{{ route('front.ateliers') }}" class="tr-pilule est-claire">Explorer la carte</a>
            </div>
            {{-- Même carte que la page Ateliers. Marqueurs vides au rendu : accueil.js les charge depuis
                 carte.json, donc l'accueil ne lit pas la base et s'affiche même si Atlas est injoignable. --}}
            <div class="acc-carte s2">
                <x-atelier-map
                    :markers="[]"
                    mode="accueil"
                    label="Carte des ateliers de réparation"
                    :endpoint="route('front.ateliers.map')"
                    alternative="La page Ateliers présente les mêmes ateliers sous forme de liste, avec leurs adresses."
                />
            </div>
        </div>
    </section>

    <section class="acc-section" aria-labelledby="acc-pourquoi-titre">
        <div class="acc-entete">
            <h2 class="acc-titre" id="acc-pourquoi-titre">La mode<br>autrement.</h2>
        </div>
        <div class="tr-rang acc-raisons" data-tr-fil>
            <p class="acc-intro">Des outils concrets pour prolonger la vie de vos vêtements.</p>
            <div>
                <h3>Économie circulaire</h3>
                <p>Réduisez le gaspillage en donnant une nouvelle histoire à chaque pièce.</p>
                <a href="#parcours" class="tr-lien">Voir le parcours</a>
            </div>
            <div>
                <h3>Ateliers locaux</h3>
                <p>Un réseau de savoir-faire près de chez vous, pour une mode qui crée du lien.</p>
                <a href="{{ route('front.ateliers') }}" class="tr-lien">Découvrir le réseau</a>
            </div>
            <div>
                <h3>Impact mesurable</h3>
                <p>Chaque action est comptabilisée pour rendre votre engagement visible.</p>
                <a href="#chiffres" class="tr-lien">Voir les chiffres</a>
            </div>
        </div>
    </section>
</main>
@endsection
