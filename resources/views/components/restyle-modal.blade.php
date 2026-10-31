@props(['disponible' => false])

@if($disponible)
<div class="modal-backdrop" id="restyle-modal" aria-hidden="true">
    <div class="modal modal-declarer modal-restyle" role="dialog" aria-modal="true" aria-labelledby="restyle-titre">
        <header class="decl-entete">
            <div>
                <p class="decl-kicker">Restyle</p>
                <h2 id="restyle-titre">Voir la réparation</h2>
            </div>
            <button type="button" class="decl-fermer" data-close-modal aria-label="Fermer">
                <i data-lucide="x"></i>
            </button>
        </header>

        <form id="restyle-formulaire" class="decl-form" data-restyle-form data-restyle-url="{{ route('front.vetements.restyle') }}" data-restyle-guide-url="{{ route('front.vetements.restyle.guide') }}" action="{{ route('front.vetements.restyle') }}" method="post" enctype="multipart/form-data">
            @csrf
            <section class="decl-etape">
                <h3>1 · La photo d'origine</h3>
                <p class="decl-aide">L'image générée reprend cette pièce : sa forme, sa coupe et ses détails restent, seule la réparation change. Sans quota Gemini, un générateur gratuit utilise la même photo et peut prendre une minute.</p>
                <label class="upload-zone decl-depot" data-upload-zone>
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" hidden data-upload-input data-restyle-photo>
                    <img alt="Photo d'origine" hidden data-upload-preview data-restyle-apercu class="upload-preview">
                    <span class="decl-depot__icone" aria-hidden="true"><i data-lucide="camera"></i></span>
                    <span class="decl-depot__texte">
                        <strong data-upload-label>Déposez la photo d'origine</strong>
                        <span>ou cliquez pour parcourir · JPG, PNG, WebP · 5 Mo</span>
                    </span>
                </label>
            </section>

            <section class="decl-etape">
                <h3>2 · La réparation choisie</h3>
                <p class="decl-aide">Décrivez ce que l'atelier doit faire. L'instruction part avec la photo.</p>
                <div class="restyle-suggestions">
                    @foreach([
                        'Reprendre l\'ourlet décousu au fil assorti',
                        'Recoudre la couture qui a lâché',
                        'Poser une pièce contrastée sur le trou',
                        'Remplacer le bouton manquant',
                    ] as $suggestion)
                        <button type="button" class="restyle-suggestion" data-restyle-suggestion>{{ $suggestion }}</button>
                    @endforeach
                </div>
                <label class="decl-description">Instruction
                    <textarea name="instruction" data-restyle-instruction rows="3" required minlength="8" maxlength="400" placeholder="Ex. Reprendre l'ourlet décousu au fil assorti, sans changer la couleur du jean."></textarea>
                </label>
            </section>

            <p class="style-message" data-restyle-message hidden></p>

            <div class="restyle-comparaison" data-restyle-resultat hidden>
                <figure>
                    <img alt="État avant la réparation" data-restyle-avant>
                    <figcaption>État avant</figcaption>
                </figure>
                <figure>
                    <img alt="État après la réparation" data-restyle-apres>
                    <figcaption>État après</figcaption>
                </figure>
            </div>

            <section class="restyle-guide" data-restyle-guide hidden>
                <p class="decl-kicker">Guide enregistré</p>
                <h3 data-restyle-guide-titre>Guide de réparation</h3>
                <p data-restyle-guide-description></p>
                <dl>
                    <dt>État avant</dt>
                    <dd data-restyle-guide-etat-avant></dd>
                    <dt>État après</dt>
                    <dd data-restyle-guide-etat-apres></dd>
                </dl>
            </section>

        </form>
        <div class="restyle-barre">
            <button type="button" class="btn btn-primary" data-restyle-guide-envoyer>Enregistrer le guide</button>
            <p class="decl-aide">La description, l'état avant et l'état après partent par e-mail à {{ auth()->user()->email }}.</p>
            <div class="restyle-pied">
                <button type="button" class="btn btn-secondary" data-close-modal>Fermer</button>
                <button type="submit" class="btn btn-primary" form="restyle-formulaire" data-restyle-envoyer>Générer l'image <i data-lucide="sparkles"></i></button>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script src="{{ asset('js/restyle-vetement.js') }}?v={{ filemtime(public_path('js/restyle-vetement.js')) }}"></script>
@endpush
@endif
