@props(['disponible' => false])

@if($disponible)
<div class="chat-vetement" data-chat-vetement data-chat-url="{{ route('front.vetements.chat') }}">
    <button type="button" class="chat-vetement-toggle" data-chat-toggle aria-expanded="false" aria-controls="chat-vetement-panel" aria-label="Conseiller textile">
        <i data-lucide="message-circle" aria-hidden="true"></i>
        <span>Conseiller textile</span>
    </button>
    <section id="chat-vetement-panel" class="chat-vetement-panel" data-chat-panel hidden>
        <header class="chat-vetement-head">
            <div>
                <strong>Conseiller textile</strong>
                <p>Réparation, don et recyclage uniquement.</p>
            </div>
            <button type="button" class="icon-btn" data-chat-fermer aria-label="Fermer le conseiller"><i data-lucide="x" aria-hidden="true"></i></button>
        </header>
        <div class="chat-vetement-messages" data-chat-messages role="log" aria-live="polite">
            <p class="chat-vetement-bulle chat-vetement-bulle--bot">Bonjour. Je réponds seulement à propos de vos vêtements : que réparer, que donner, que recycler.</p>
        </div>
        <form class="chat-vetement-form" data-chat-form>
            @csrf
            <label class="sr-only" for="chat-vetement-message">Votre question</label>
            <input id="chat-vetement-message" name="message" type="text" maxlength="500" required placeholder="Ex. Ma veste est-elle réparable ?" autocomplete="off" data-chat-input>
            <button type="submit" class="btn btn-primary small" data-chat-envoyer>Envoyer</button>
        </form>
    </section>
</div>
@endif
