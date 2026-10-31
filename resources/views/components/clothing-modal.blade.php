@props(['disponible' => false])

<div class="modal-backdrop" id="clothing-modal" aria-hidden="true">
    <div class="modal modal-declarer" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <header class="decl-entete">
            <div>
                <p class="decl-kicker">Nouveau parcours</p>
                <h2 id="modal-title">Déclarer un vêtement</h2>
            </div>
            <button type="button" class="decl-fermer" data-close-modal aria-label="Fermer">
                <i data-lucide="x"></i>
            </button>
        </header>

        <form class="decl-form" action="{{ route('front.vetements.store') }}" method="post" enctype="multipart/form-data">
            @csrf
            @if(isset($errors) && $errors->any())
                <div class="decl-erreurs" role="alert">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <section class="decl-etape" aria-labelledby="decl-photo-titre">
                <h3 id="decl-photo-titre">1 · La photo</h3>
                @if($disponible)
                    <p class="decl-aide">Une photo nette suffit : l'analyse propose le type, la matière, l'état et si une réparation a une deuxième chance. Vous pouvez tout corriger.</p>
                @else
                    <p class="decl-aide">Ajoutez une photo de la pièce. JPG, PNG ou WebP, 5 Mo maximum.</p>
                    @if(app()->environment('local'))
                        <p class="decl-aide">L'analyse automatique est inactive tant que GEMINI_API_KEY ou OPENAI_API_KEY n'est pas renseignée.</p>
                    @endif
                @endif

                <label class="upload-zone decl-depot" data-upload-zone @if($disponible) data-analyse-url="{{ route('front.vetements.analyser') }}" @endif>
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" hidden data-upload-input>
                    <img alt="Aperçu de la photo choisie" hidden data-upload-preview class="upload-preview">
                    <span class="decl-depot__icone" aria-hidden="true"><i data-lucide="camera"></i></span>
                    <span class="decl-depot__texte">
                        <strong data-upload-label>Déposez la photo ici</strong>
                        <span>ou cliquez pour parcourir · JPG, PNG, WebP · 5 Mo</span>
                    </span>
                </label>

                <div class="analyse-ia" data-analyse-panel hidden role="status" aria-live="polite">
                    <div class="analyse-ia-head">
                        <i data-lucide="sparkles"></i>
                        <strong data-analyse-title>Analyse de la photo</strong>
                        <span class="analyse-badge" data-analyse-badge></span>
                    </div>
                    <p data-analyse-resume></p>
                    <ul data-analyse-defauts></ul>
                </div>
            </section>

            <section class="decl-etape" aria-labelledby="decl-piece-titre">
                <h3 id="decl-piece-titre">2 · La pièce</h3>
                <div class="form-grid decl-grille">
                    <label>Type de vêtement
                        <select name="type" required>
                            <option value="">Choisir un type</option>
                            @foreach(\App\Modules\Vetements\Models\Vetement::TYPES as $type)
                                <option value="{{ $type }}" @selected(old('type') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Taille
                        <select name="size" required>
                            <option value="">Choisir une taille</option>
                            @foreach(['XS', 'S', 'M', 'L', 'XL', '42', '44', '46'] as $size)
                                <option value="{{ $size }}" @selected(old('size') === $size)>{{ $size }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="span-2">Matière
                        <input type="text" name="material" value="{{ old('material') }}" list="vetement-materials" required placeholder="Coton, denim, laine…">
                        <datalist id="vetement-materials">
                            @foreach(\App\Modules\Vetements\Models\Vetement::MATERIALS as $material)
                                <option value="{{ $material }}"></option>
                            @endforeach
                        </datalist>
                    </label>
                </div>

                <fieldset class="decl-champ">
                    <legend>État du vêtement</legend>
                    <div class="radio-row" data-condition-radios>
                        @foreach(\App\Modules\Vetements\Models\Vetement::ETATS as $state)
                            <label class="radio @if(old('condition_label', 'Bon état') === $state) active @endif">
                                <input type="radio" name="condition_label" value="{{ $state }}" @checked(old('condition_label', 'Bon état') === $state) required>
                                {{ $state }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            </section>

            <section class="decl-etape" aria-labelledby="decl-suite-titre">
                <h3 id="decl-suite-titre">3 · La suite</h3>
                <fieldset class="decl-champ">
                    <legend>Que souhaitez-vous faire ?</legend>
                    <div class="action-choice-grid" data-action-radios>
                        <label class="action-choice @if(old('intended_action', 'reparation') === 'reparation') active @endif">
                            <input type="radio" name="intended_action" value="reparation" @checked(old('intended_action', 'reparation') === 'reparation') required>
                            <i data-lucide="wrench"></i>
                            <strong>Réparer</strong>
                            <span>Retouches, couture, remise en état</span>
                        </label>
                        <label class="action-choice @if(old('intended_action') === 'don') active @endif">
                            <input type="radio" name="intended_action" value="don" @checked(old('intended_action') === 'don')>
                            <i data-lucide="gift"></i>
                            <strong>Donner</strong>
                            <span>Offrir à une association</span>
                        </label>
                    </div>
                </fieldset>

                <label class="decl-description">Description
                    <textarea name="description" rows="3" placeholder="Couleur, défauts, ce qu'il faudrait reprendre…">{{ old('description') }}</textarea>
                </label>
            </section>

            <div class="modal-actions decl-actions">
                <button type="button" class="btn btn-secondary" data-close-modal>Annuler</button>
                <button type="submit" class="btn btn-primary">Déclarer le vêtement <i data-lucide="arrow-right"></i></button>
            </div>
        </form>
    </div>
</div>
