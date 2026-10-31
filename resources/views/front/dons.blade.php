@extends('layouts.trame')

@section('title', 'Proposer un don')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/client-trame.css') }}">
@endpush

@section('bandeau')
    <nav class="tr-ariane" aria-label="Fil d'Ariane">
        <ol>
            <li><a href="{{ route('front.home') }}">Accueil</a></li>
            <li><a href="{{ route('front.associations') }}">Associations</a></li>
            <li><span aria-current="page">Dons</span></li>
        </ol>
    </nav>
    <div class="tr-bandeau__grille">
        <div class="tr-bandeau__principal">
            <h1>Proposer un don</h1>
            <p class="tr-bandeau__intro">Donnez une seconde vie à vos vêtements en les offrant à ceux qui en ont besoin.</p>
        </div>
        <div class="tr-bandeau__cote">
            <button type="button" class="tr-pilule est-claire" data-open-modal="don-modal"
                @disabled($vetements->isEmpty() || $associations->isEmpty())>
                <i data-lucide="gift" aria-hidden="true"></i> Nouveau don
            </button>
        </div>
    </div>
@endsection

@section('content')
<main class="container page-content client-page">

    {{-- Associations avec leurs besoins --}}
    @if($associations->isNotEmpty())
        <section class="section-block">
            <h2 class="section-title"><i data-lucide="heart-handshake"></i> Associations & leurs besoins</h2>
            <div class="association-grid">
                @foreach($associations as $asso)
                    <article class="association-card clickable" data-asso-id="{{ $asso->_id }}">
                        <div class="association-head">
                            <div class="association-logo"><i data-lucide="heart-handshake"></i></div>
                            <x-status-badge tone="purple">{{ $asso->matchScore() }}% match</x-status-badge>
                        </div>
                        <h3>{{ $asso->nom }}</h3>
                        <p class="muted small">{{ Str::limit($asso->description, 80) }}</p>
                        @if(!empty($asso->besoins))
                            <div class="besoins-tags">
                                @foreach(array_slice($asso->besoins, 0, 3) as $besoin)
                                    <span class="tag">{{ $besoin['type'] ?? '' }}
                                        @if(!empty($besoin['taille'])) · {{ $besoin['taille'] }}@endif
                                    </span>
                                @endforeach
                            </div>
                        @endif
                        <button type="button" class="btn btn-secondary full select-asso-btn"
                                data-asso="{{ $asso->_id }}" data-open-modal="don-modal">
                            Sélectionner <i data-lucide="check"></i>
                        </button>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Historique de mes dons --}}
    @if($dons->isNotEmpty())
        <section class="section-block">
            <h2 class="section-title"><i data-lucide="clock"></i> Mes propositions de don</h2>
            <div class="panel table-panel">
                <table>
                    <caption class="sr-only">Mes propositions de don</caption>
                    <thead>
                        <tr>
                            <th scope="col">Vêtement</th>
                            <th scope="col">Association</th>
                            <th scope="col">Message</th>
                            <th scope="col">Statut</th>
                            <th scope="col">Date</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($dons as $don)
                            <tr>
                                <td><b>{{ $don->vetement?->displayName() ?? '—' }}</b></td>
                                <td>{{ $don->association?->nom ?? '—' }}</td>
                                <td class="muted small">{{ Str::limit($don->message, 50) ?: '—' }}</td>
                                <td>
                                    <x-status-badge :tone="$don->statutTone()">
                                        {{ $don->statutLabel() }}
                                    </x-status-badge>
                                </td>
                                <td class="muted small">{{ $don->created_at->format('d/m/Y') }}</td>
                                <td>
                                    @if($don->isPending())
                                        <div style="display: flex; gap: 8px;">
                                            <button type="button" class="icon-btn edit-don-btn" aria-label="Modifier le don de {{ $don->vetement?->displayName() ?? 'ce vêtement' }}"
                                                    data-id="{{ $don->_id }}"
                                                    data-vetement="{{ $don->vetement_id }}"
                                                    data-association="{{ $don->association_id }}"
                                                    data-message="{{ $don->message }}"
                                                    data-open-modal="don-modal">
                                                <i data-lucide="edit-2" aria-hidden="true"></i>
                                            </button>
                                            <form method="POST"
                                                  action="{{ route('front.dons.destroy', $don->_id) }}"
                                                  onsubmit="return confirm('Annuler cette proposition ?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="icon-btn danger" aria-label="Annuler le don de {{ $don->vetement?->displayName() ?? 'ce vêtement' }}">
                                                    <i data-lucide="x" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if($vetements->isEmpty())
        <div class="empty-state">
            <i data-lucide="shirt"></i>
            <p>Vous n'avez pas encore de vêtements éligibles au don.</p>
            <a href="{{ route('front.vetements') }}" class="btn btn-primary">
                <i data-lucide="plus"></i> Déclarer un vêtement
            </a>
        </div>
    @endif

</main>
@endsection

@push('overlays')
<div class="modal-backdrop" id="don-modal" aria-hidden="true">
    <div class="modal" role="dialog" aria-labelledby="don-modal-title">
        <div class="modal-head">
            <div>
                <p class="kicker">Économie circulaire</p>
                <h2 id="don-modal-title">Proposer un don</h2>
            </div>
            <button type="button" class="icon-btn" data-close-modal aria-label="Fermer">
                <i data-lucide="x"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('front.dons.store') }}" id="don-form">
            @csrf
            <input type="hidden" name="_method" id="form-method" disabled>

            @if(isset($errors) && $errors->any())
                <div class="field-error" style="margin-bottom:12px">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            {{-- Sélection du vêtement --}}
            <fieldset class="field" style="border:0;padding:0;margin:0 0 16px">
                <legend style="margin-bottom:8px;font-size:13px;font-weight:600">
                    Quel vêtement souhaitez-vous donner ?
                </legend>
                @if($vetements->isEmpty())
                    <div class="alert-info">
                        <i data-lucide="info"></i>
                        Aucun vêtement disponible.
                        <a href="{{ route('front.vetements') }}">Déclarer un vêtement</a>
                    </div>
                @else
                    <div class="vetement-choice-grid" id="vetement-cards">
                        @foreach($vetements as $vet)
                            <label class="action-choice vetement-choice {{ old('vetement_id', request('vetement')) === (string)$vet->_id ? 'active' : '' }}">
                                <input type="radio" name="vetement_id" value="{{ $vet->_id }}"
                                    @checked(old('vetement_id', request('vetement')) === (string)$vet->_id) required>
                                <i data-lucide="shirt"></i>
                                <strong>{{ $vet->displayName() }}</strong>
                                <span>{{ $vet->statusLabel() }} · {{ $vet->condition_label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('vetement_id')<span class="form-error" style="margin-top:6px;display:block">{{ $message }}</span>@enderror
                @endif
            </fieldset>

            {{-- Sélection de l'association --}}
            <fieldset class="field" style="border:0;padding:0;margin:0 0 16px">
                <legend style="margin-bottom:8px;font-size:13px;font-weight:600">
                    À quelle association ?
                </legend>
                @if($associations->isEmpty())
                    <div class="alert-info">
                        <i data-lucide="info"></i>
                        Aucune association active pour le moment.
                    </div>
                @else
                    <div class="action-choice-grid" id="asso-cards">
                        @foreach($associations as $asso)
                            <label class="action-choice {{ old('association_id', request('association')) === (string)$asso->_id ? 'active' : '' }}"
                                   data-asso-radio="{{ $asso->_id }}">
                                <input type="radio" name="association_id" value="{{ $asso->_id }}"
                                    @checked(old('association_id', request('association')) === (string)$asso->_id) required>
                                <i data-lucide="heart-handshake"></i>
                                <strong>{{ $asso->nom }}</strong>
                                <span>{{ $asso->matchScore() }}% match
                                    @if(!empty($asso->besoins))
                                        · {{ $asso->besoins[0]['type'] ?? '' }}
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('association_id')<span class="form-error" style="margin-top:6px;display:block">{{ $message }}</span>@enderror
                @endif
            </fieldset>

            {{-- Message optionnel --}}
            <label>Message d'accompagnement <small class="field-hint">(optionnel)</small>
                <textarea name="message" id="message" rows="3" maxlength="500"
                    placeholder="Ex : Pantalon en bon état, taille 42, porté 3 fois seulement...">{{ old('message') }}</textarea>
            </label>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" data-close-modal>Annuler</button>
                <button type="submit" class="btn btn-primary"
                    {{ ($vetements->isEmpty() || $associations->isEmpty()) ? 'disabled' : '' }}>
                    Soumettre <i data-lucide="send"></i>
                </button>
            </div>
        </form>
    </div>
</div>
@endpush

@push('scripts')
<script>
// Activer la radio card vetement
document.addEventListener('change', e => {
    if (e.target.name === 'vetement_id') {
        document.querySelectorAll('.vetement-choice').forEach(l => l.classList.remove('active'));
        e.target.closest('.vetement-choice')?.classList.add('active');
    }
    if (e.target.name === 'association_id') {
        document.querySelectorAll('#asso-cards .action-choice').forEach(l => l.classList.remove('active'));
        e.target.closest('.action-choice')?.classList.add('active');
    }
});

// Clic sur "Sélectionner" d'une card association → pré-sélectionne dans le modal
document.querySelectorAll('.select-asso-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        // Reset form to creation mode FIRST
        resetDonModalToCreate();

        const id = btn.dataset.asso;
        const radio = document.querySelector(`[data-asso-radio="${id}"] input`);
        if (radio) {
            radio.checked = true;
            radio.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });
});

// Clic sur le bouton "Nouveau don"
document.querySelector('[data-open-modal="don-modal"]')?.addEventListener('click', (e) => {
    if(!e.target.closest('.edit-don-btn') && !e.target.closest('.select-asso-btn')) {
        resetDonModalToCreate();
    }
});

function resetDonModalToCreate() {
    const form = document.getElementById('don-form');
    if(!form) return;
    form.action = "{{ route('front.dons.store') }}";
    document.getElementById('don-modal-title').textContent = "Proposer un don";
    const methodInput = document.getElementById('form-method');
    if(methodInput) methodInput.disabled = true;
    
    // Clear selection
    form.reset();
    document.querySelectorAll('.vetement-choice').forEach(l => l.classList.remove('active'));
    document.querySelectorAll('#asso-cards .action-choice').forEach(l => l.classList.remove('active'));
}

// Clic sur "Modifier" un don
document.querySelectorAll('.edit-don-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const id = btn.dataset.id;
        const vetementId = btn.dataset.vetement;
        const associationId = btn.dataset.association;
        const message = btn.dataset.message;

        const form = document.getElementById('don-form');
        form.action = "{{ url('dons') }}/" + id;
        
        document.getElementById('don-modal-title').textContent = "Modifier la proposition";
        
        const methodInput = document.getElementById('form-method');
        methodInput.value = "PUT";
        methodInput.disabled = false;

        // Pré-sélectionner le vêtement
        const vetRadio = form.querySelector(`input[name="vetement_id"][value="${vetementId}"]`);
        if (vetRadio) {
            vetRadio.checked = true;
            vetRadio.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // Pré-sélectionner l'association
        const assoRadio = form.querySelector(`input[name="association_id"][value="${associationId}"]`);
        if (assoRadio) {
            assoRadio.checked = true;
            assoRadio.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // Remplir le message
        const msgTextarea = form.querySelector('textarea[name="message"]');
        if (msgTextarea) {
            msgTextarea.value = message || '';
        }
    });
});

// Si erreurs Laravel → rouvrir le modal auto
@if($errors->any())
    document.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('don-modal');
        if (modal) {
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
        }
    });
@endif
</script>
@endpush
