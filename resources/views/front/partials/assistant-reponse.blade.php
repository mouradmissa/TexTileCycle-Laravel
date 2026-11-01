{{-- Un échange (question du visiteur + réponse de l'assistant). Rendu par la page, ou renvoyé en JSON à js/assistant.js. --}}
@php
    $horsSujet = $resultat['statut'] === \App\Modules\Ateliers\Services\AssistantService::STATUT_HORS_SUJET;
    $extraits = $resultat['statut'] === \App\Modules\Ateliers\Services\AssistantService::STATUT_EXTRAITS;
    $paragraphes = preg_split('/\n{2,}/', $resultat['reponse']) ?: [];
@endphp
<li class="as-message as-message--visiteur">
    <div class="as-bulle"><p>{{ $question }}</p></div>
</li>
<li class="as-message as-message--assistant">
    <span class="as-avatar" aria-hidden="true"><i data-lucide="sparkles"></i></span>
    <div class="as-bulle">
        @foreach ($paragraphes as $paragraphe)
            <p>{{ $paragraphe }}</p>
        @endforeach

        @if ($extraits)
            <p class="as-note">La rédaction automatique est indisponible : voici directement nos fiches.</p>
        @endif

        @if ($resultat['sources'] !== [])
            <div class="as-sources">
                <p class="as-sources__titre">Sources</p>
                <ol>
                    @foreach ($resultat['sources'] as $source)
                        {{-- Fiche repliée ; ouverte d'office quand il n'y a pas de texte rédigé. --}}
                        <li @class(['as-source', 'is-citee' => $source['citee']])>
                            <details @if ($extraits && $loop->first) open @endif>
                                <summary>
                                    <span class="as-source__num">[{{ $source['numero'] }}]</span>
                                    {{ $source['titre'] }}
                                    @if ($source['citee'])
                                        <span class="as-source__badge">citée</span>
                                    @endif
                                </summary>
                                <p>{{ $source['texte'] }}</p>
                            </details>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif

        @if ($resultat['reparations'] !== [])
            <div class="as-reparations">
                <p class="as-sources__titre">Faire réparer près de chez vous</p>
                <ul>
                    @foreach ($resultat['reparations'] as $reparation)
                        <li>
                            <a href="{{ route('front.ateliers', ['service' => $reparation['nom']]) }}">
                                <i data-lucide="scissors" aria-hidden="true"></i>
                                {{ $reparation['nom'] }}
                                <span>{{ $reparation['nb_ateliers'] }} atelier{{ $reparation['nb_ateliers'] > 1 ? 's' : '' }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($horsSujet)
            <p class="as-note"><a href="{{ route('front.ateliers.diagnostic') }}">Essayer le diagnostic par photo</a></p>
        @endif
    </div>
</li>
