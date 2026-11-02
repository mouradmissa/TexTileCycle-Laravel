{{-- Contenu de l'assistant atelier : fil, boutons rapides, champ. Utilisé dans le panneau flottant et sur la page dédiée.
     Paramètres : $question et $reponse (réponse rendue par le serveur sans JavaScript), $idPrefixe (identifiants uniques). --}}
@php
    $erreurQuestion = $errors->first('question') ?: $errors->first('outil');
@endphp
<ol class="aa-fil" data-aa-fil aria-live="polite">
    <li class="aa-msg aa-msg--assistant">
        <p>Bonjour ! Posez une question sur votre atelier, par exemple « Quelles garanties expirent ce mois-ci ? »</p>
    </li>
    @if ($reponse)
        @include('back.ateliers.espace.partials.assistant-reponse', ['question' => $question, 'reponse' => $reponse])
    @endif
</ol>

{{-- Contrôles de saisie en PHP (AssistantAtelierRequest) : aucun attribut de validation HTML. --}}
<form method="post" action="{{ route('back.ateliers.assistant.repondre') }}" class="aa-form" data-aa-form novalidate>
    @csrf
    <p class="aa-alerte" role="alert" data-aa-alerte hidden></p>

    <label for="{{ $idPrefixe }}-question" class="sr-only">Votre question</label>
    <div class="aa-ligne">
        <input id="{{ $idPrefixe }}-question" name="question" type="text" autocomplete="off" value="{{ old('question') }}"
               placeholder="Ex. : combien de rendez-vous ce mois-ci ?" data-aa-question
               @if ($erreurQuestion) aria-invalid="true" aria-describedby="{{ $idPrefixe }}-erreur" @endif>
        <button type="submit" class="btn btn-primary small" data-aa-envoyer>
            <i data-lucide="send" aria-hidden="true"></i><span class="sr-only">Envoyer</span>
        </button>
    </div>
    <p id="{{ $idPrefixe }}-erreur" class="aa-erreur" data-aa-erreur @unless ($erreurQuestion) hidden @endunless>{{ $erreurQuestion }}</p>
    {{-- Après le bouton Envoyer : Entrée dans le champ envoie la question, pas le premier raccourci. --}}
    <div class="aa-raccourcis" role="group" aria-label="Questions rapides">
        @foreach (\App\Modules\Ateliers\Services\AssistantAtelierService::RACCOURCIS as $outil => $libelle)
            <button type="submit" name="outil" value="{{ $outil }}" class="aa-raccourci" data-aa-raccourci data-libelle="{{ $libelle }}">{{ $libelle }}</button>
        @endforeach
    </div>

    <p class="aa-note">Seule votre question est envoyée à Gemini (Google) pour choisir la bonne recherche ; vos données restent sur TexTileCycle.</p>
</form>
