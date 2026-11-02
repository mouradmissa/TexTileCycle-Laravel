{{-- En-tête commun de l'espace atelier. Paramètres : $atelier, $onglet ('profil' | 'services' | 'passeports'), $nbServices. --}}
@php
    $statut = $atelier->statut;
    $onglets = [
        'profil' => ['route' => 'back.ateliers.profil', 'label' => 'Mon profil', 'icon' => 'store'],
        'services' => ['route' => 'back.ateliers.services', 'label' => 'Mes services', 'icon' => 'scissors'],
        'passeports' => ['route' => 'back.ateliers.passeports', 'label' => 'Passeports', 'icon' => 'qr-code'],
        'rdv' => ['route' => 'back.rdv', 'label' => 'Rendez-vous', 'icon' => 'calendar-days'],
        'pieces' => ['route' => 'back.vetements', 'label' => 'Pièces à traiter', 'icon' => 'shirt'],
    ];
@endphp

<header class="panel ab-espace-head">
    <div class="ab-espace-head__main">
        <span @class(['ab-avatar', 'ab-avatar--lg', 'ab-avatar--'.$atelier->avatarTone()]) aria-hidden="true">{{ $atelier->initiales() }}</span>
        <div class="ab-espace-head__identity">
            <p class="kicker">Mon atelier</p>
            <h2 class="ab-espace-head__name">{{ $atelier->nom }}</h2>
            @if ($atelier->ville)
                <p class="ab-espace-head__meta"><i data-lucide="map-pin" aria-hidden="true"></i> {{ $atelier->ville }}</p>
            @endif
        </div>
        <div class="ab-espace-head__aside">
            <x-status-badge :tone="$atelier->statutTone()">{{ $atelier->statutLabel() }}</x-status-badge>
            @if ($statut === \App\Modules\Ateliers\Models\Atelier::STATUT_ACTIF)
                <a href="{{ route('front.ateliers.show', ['id' => (string) $atelier->getKey()]) }}" class="btn btn-secondary small" target="_blank" rel="noopener">
                    <i data-lucide="external-link" aria-hidden="true"></i> Voir ma fiche publique
                    <span class="sr-only">(nouvel onglet)</span>
                </a>
            @endif
        </div>
    </div>

    <nav class="ab-tabs" aria-label="Sections de mon atelier">
        @foreach ($onglets as $cle => $item)
            <a href="{{ route($item['route']) }}"
               @class(['ab-tab', 'is-active' => $onglet === $cle])
               @if ($onglet === $cle) aria-current="page" @endif>
                <i data-lucide="{{ $item['icon'] }}" aria-hidden="true"></i>
                {{ $item['label'] }}
                @if ($cle === 'services')
                    <span class="ab-tab__count" aria-label="{{ $nbServices }} {{ $nbServices > 1 ? 'services' : 'service' }}">{{ $nbServices }}</span>
                @elseif ($cle === 'rdv' && isset($nbRdv) && $nbRdv !== null)
                    <span class="ab-tab__count">{{ $nbRdv }}</span>
                @elseif ($cle === 'pieces' && isset($nbPieces) && $nbPieces !== null)
                    <span class="ab-tab__count">{{ $nbPieces }}</span>
                @endif
            </a>
        @endforeach
    </nav>
</header>

@if ($statut === \App\Modules\Ateliers\Models\Atelier::STATUT_SUSPENDU)
    <div class="ab-alert ab-alert--suspended" role="status">
        <i data-lucide="circle-pause" aria-hidden="true"></i>
        <p><strong>Votre atelier est suspendu, contactez l'administrateur.</strong> Il n'apparaît plus sur le site ni sur la carte.</p>
    </div>
@elseif ($statut !== \App\Modules\Ateliers\Models\Atelier::STATUT_ACTIF)
    <div class="ab-alert ab-alert--warning" role="status">
        <i data-lucide="hourglass" aria-hidden="true"></i>
        <p><strong>Votre atelier est en cours de validation, il n'est pas encore visible sur la carte.</strong> Vous pouvez déjà compléter votre profil et vos services.</p>
    </div>
@endif

{{-- Assistant de l'atelier : bouton flottant sur toutes les pages de l'espace, sauf sur sa propre page. --}}
@if (($onglet ?? null) !== 'assistant')
    @include('back.ateliers.espace.partials.assistant-flottant')
@endif
