{{-- Assistant de réservation (IA du module Rendez-vous) : bulle flottante, même gabarit que le conseiller textile. --}}
<div class="chat-vetement assistant-rdv" data-assistant-rdv
     data-url="{{ route('front.rdv.ia.assistant') }}"
     data-url-reserver="{{ route('front.rdv.ia.reserver') }}">
    <button type="button" class="chat-vetement-toggle" data-assistant-toggle aria-expanded="false" aria-controls="assistant-rdv-panel">
        <i data-lucide="calendar-clock" aria-hidden="true"></i>
        <span>Assistant RDV</span>
    </button>
    <section id="assistant-rdv-panel" class="chat-vetement-panel" data-assistant-panel hidden>
        <header class="chat-vetement-head">
            <div>
                <strong>Assistant de réservation</strong>
                <p>Décrivez votre besoin, je trouve le meilleur créneau.</p>
            </div>
            <button type="button" class="icon-btn" data-assistant-fermer aria-label="Fermer l'assistant"><i data-lucide="x" aria-hidden="true"></i></button>
        </header>
        <div class="chat-vetement-messages" data-assistant-messages role="log" aria-live="polite">
            <p class="chat-vetement-bulle chat-vetement-bulle--bot">Bonjour ! Dites-moi ce que vous voulez faire réparer et quand, par exemple : « Recoudre mon pull jeudi après-midi ».</p>
        </div>
        <form class="chat-vetement-form" data-assistant-form>
            @csrf
            <label class="sr-only" for="assistant-rdv-message">Votre demande</label>
            <input id="assistant-rdv-message" type="text" maxlength="500" required placeholder="Ex. Raccourcir mon jean demain matin" autocomplete="off" data-assistant-input>
            <button type="submit" class="btn btn-primary small">Envoyer</button>
        </form>
    </section>
</div>

@pushOnce('styles', 'assistant-rdv-css')
    <link rel="stylesheet" href="{{ asset('css/assistant-rdv.css') }}">
@endPushOnce
@pushOnce('scripts', 'assistant-rdv-js')
    <script src="{{ asset('js/assistant-rdv.js') }}" defer></script>
@endPushOnce
