@extends('layouts.trame')

@section('title', 'Assistant réparation · Ateliers')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/assistant.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/assistant.js') }}" defer></script>
@endpush

@php $erreurQuestion = $errors->first('question'); @endphp

@section('bandeau')
    <nav class="tr-ariane" aria-label="Fil d'Ariane">
        <ol>
            <li><a href="{{ route('front.home') }}">Accueil</a></li>
            <li><a href="{{ route('front.ateliers') }}">Ateliers</a></li>
            <li><span aria-current="page">Assistant réparation</span></li>
        </ol>
    </nav>
    <div class="tr-bandeau__grille">
        <div class="tr-bandeau__principal">
            <h1>Assistant réparation</h1>
            <p class="tr-bandeau__intro">Une tache, un trou, une fermeture qui coince ? Posez votre question : l'assistant répond à partir
               des fiches conseils de TexTileCycle, cite ses sources et vous oriente vers les ateliers qui font la réparation.</p>
        </div>
    </div>
@endsection

@section('content')
<main class="container page-content as">
    <div class="as-layout">
        <section class="as-fil" aria-labelledby="as-titre">
            <h2 id="as-titre" class="tr-sr">Conversation avec l'assistant</h2>

            {{-- Fil de la conversation : js/assistant.js y ajoute chaque réponse, annoncée aux lecteurs d'écran. --}}
            <ol class="as-conversation" data-as-conversation aria-live="polite">
                <li class="as-message as-message--assistant">
                    <span class="as-avatar" aria-hidden="true"><i data-lucide="sparkles"></i></span>
                    <div class="as-bulle">
                        <p>Bonjour ! Décrivez le problème de votre vêtement, ou choisissez une question ci-dessous.</p>
                    </div>
                </li>
                @if ($resultat)
                    @include('front.partials.assistant-reponse', ['question' => $question, 'resultat' => $resultat])
                @endif
            </ol>

            {{-- Contrôles de saisie en PHP (AssistantRequest) : aucun attribut de validation HTML. --}}
            <form method="post" action="{{ route('front.ateliers.assistant.repondre') }}" class="as-form tr-fil" data-as-form novalidate>
                @csrf

                <div data-as-alerte>
                    @if (session('assistant_erreur'))
                        <p class="as-alerte" role="alert">{{ session('assistant_erreur') }}</p>
                    @endif
                </div>

                <label for="as-question" class="as-form__label">Votre question</label>
                <div class="as-form__ligne">
                    <textarea id="as-question" name="question" rows="2" data-as-question
                              placeholder="Ex. : du café sur ma chemise blanche, comment l'enlever ?"
                              @if ($erreurQuestion) aria-invalid="true" aria-describedby="as-question-erreur" @endif>{{ old('question') }}</textarea>
                    <button type="submit" class="btn btn-primary as-form__envoyer" data-as-envoyer>
                        <i data-lucide="send" aria-hidden="true"></i> <span>Envoyer</span>
                    </button>
                </div>
                <p id="as-question-erreur" class="as-erreur" data-as-erreur @unless ($erreurQuestion) hidden @endunless>{{ $erreurQuestion }}</p>

                {{-- Des boutons d'envoi : sans JavaScript, ils posent directement la question. --}}
                <div class="as-exemples" role="group" aria-label="Questions fréquentes">
                    @foreach ($exemples as $exemple)
                        <button type="submit" name="question" value="{{ $exemple }}" class="as-exemple" data-as-exemple>{{ $exemple }}</button>
                    @endforeach
                </div>

                <p class="as-confidentialite">
                    <i data-lucide="info" aria-hidden="true"></i>
                    Votre question est envoyée au modèle Gemini (Google) pour rédiger la réponse : n'y mettez pas d'informations personnelles.
                    Rien n'est enregistré par TexTileCycle.
                </p>
            </form>
        </section>

        <aside class="as-cote" aria-label="Comment fonctionne l'assistant">
            <section class="tr-fil">
                <h2>Comment ça marche</h2>
                <ol class="as-etapes">
                    <li><strong>Recherche.</strong> Votre question est comparée à nos fiches conseils (sens de la phrase et mots-clés).</li>
                    <li><strong>Rédaction.</strong> Une IA rédige la réponse uniquement à partir des fiches trouvées, et les cite.</li>
                    <li><strong>Orientation.</strong> Si une réparation s'impose, vous voyez les ateliers qui la proposent.</li>
                </ol>
            </section>
            <section class="tr-fil">
                <h2>Une photo vaut mieux qu'un long discours</h2>
                <p>Le diagnostic par photo reconnaît le défaut et propose directement les ateliers adaptés.</p>
                <a href="{{ route('front.ateliers.diagnostic') }}" class="btn btn-secondary">
                    <i data-lucide="camera" aria-hidden="true"></i> Diagnostic par photo
                </a>
            </section>
        </aside>
    </div>
</main>
@endsection
