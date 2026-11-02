{{-- Bouton flottant de l'assistant, sur toutes les pages de l'espace atelier. Sans JavaScript, c'est un lien vers la
     page de l'assistant ; js/assistant-atelier.js en fait un bouton qui ouvre le panneau. --}}
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/assistant-atelier.css') }}">
@endpush
@push('scripts')
    <script src="{{ asset('js/assistant-atelier.js') }}" defer></script>
@endpush

<div class="aa-flottant" data-aa-flottant>
    <section class="aa-panneau" id="aa-panneau" role="dialog" aria-modal="false" aria-labelledby="aa-panneau-titre" data-aa-panneau hidden>
        <header class="aa-panneau__tete">
            <h2 id="aa-panneau-titre"><i data-lucide="sparkles" aria-hidden="true"></i> Assistant de mon atelier</h2>
            <button type="button" class="icon-btn" data-aa-fermer aria-label="Fermer l'assistant">
                <i data-lucide="x" aria-hidden="true"></i>
            </button>
        </header>
        @include('back.ateliers.espace.partials.assistant-panneau', ['question' => null, 'reponse' => null, 'idPrefixe' => 'aa-flottant'])
    </section>

    <a href="{{ route('back.ateliers.assistant') }}" class="aa-bouton" data-aa-ouvrir aria-controls="aa-panneau" aria-expanded="false">
        <i data-lucide="message-circle-question" aria-hidden="true"></i>
        <span>Assistant</span>
    </a>
</div>
