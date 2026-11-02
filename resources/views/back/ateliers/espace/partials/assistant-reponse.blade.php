{{-- Un échange de l'assistant atelier : la question (ou le raccourci) puis la réponse calculée à partir des données. --}}
@php
    $badges = [
        \App\Modules\Ateliers\Services\AssistantAtelierService::VIA_IA => 'Question comprise par l\'IA',
        \App\Modules\Ateliers\Services\AssistantAtelierService::VIA_MOTS_CLES => 'Compris par mots-clés (IA indisponible)',
    ];
@endphp
<li class="aa-msg aa-msg--moi"><p>{{ $question }}</p></li>
<li class="aa-msg aa-msg--assistant">
    <p class="aa-titre">{{ $reponse['titre'] }}</p>
    <p>{{ $reponse['texte'] }}</p>
    @if ($reponse['lignes'] !== [])
        <ul class="aa-lignes">
            @foreach ($reponse['lignes'] as $ligne)
                <li>{{ $ligne }}</li>
            @endforeach
        </ul>
    @endif
    @if ($reponse['lien'])
        <a href="{{ route($reponse['lien']) }}" class="aa-lien">Ouvrir la page <i data-lucide="arrow-right" aria-hidden="true"></i></a>
    @endif
    @isset ($badges[$reponse['via']])
        <p class="aa-via">{{ $badges[$reponse['via']] }}</p>
    @endisset
</li>
