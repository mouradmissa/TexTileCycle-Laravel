@extends('layouts.back')

@section('page-title', $pageTitle)

@section('content')
<div class="module">
    @include('back.partials.flash')
    <div class="panel form-panel" style="max-width:760px;margin:0 auto">

        {{-- En-tête style modal --}}
        <div class="modal-head" style="margin-bottom:28px;padding-bottom:20px;border-bottom:1px solid var(--border)">
            <div>
                <p class="kicker">{{ ($premiereFiche ?? false) ? 'Première connexion' : ($association ? 'Modifier' : 'Nouveau') }}</p>
                <h2 style="font-size:22px;margin:0">{{ $pageTitle }}</h2>
            </div>
            @unless($premiereFiche ?? false)
                <a href="{{ route('back.associations') }}" class="icon-btn" aria-label="Retour à la liste des associations">
                    <i data-lucide="x" aria-hidden="true"></i>
                </a>
            @endunless
        </div>

        @if($premiereFiche ?? false)
            <div class="panel" style="margin-bottom:20px;padding:14px 18px;background:#fff8e1;border:1px solid #ffe082">
                <p style="margin:0">Votre compte a été validé. Renseignez votre association : elle restera <b>en attente</b> jusqu'à validation par l'administrateur, puis sera visible pour les dons.</p>
            </div>
        @endif

        <form method="POST"
              action="{{ $association ? route('back.associations.update', $association->_id) : route('back.associations.store') }}"
              id="asso-form">
            @csrf
            @if($association) @method('PUT') @endif

            @if($errors->any())
                <div class="field-error" style="margin-bottom:20px">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            {{-- ── Section 1 : Informations générales ── --}}
            <fieldset class="field" style="border:0;padding:0;margin:0 0 28px">
                <legend style="font-size:13px;font-weight:700;margin-bottom:14px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted)">
                    Informations générales
                </legend>

                <div class="form-grid">
                    <label class="span-2">Nom de l'association <span class="required">*</span>
                        <input type="text" name="nom" class="form-control"
                               value="{{ old('nom', $association?->nom) }}"
                               placeholder="Ex : Solidarité Mode Tunis" required maxlength="150">
                        @error('nom')<span class="form-error">{{ $message }}</span>@enderror
                    </label>

                    <label>Téléphone
                        <input type="text" name="telephone" class="form-control"
                               value="{{ old('telephone', $association?->telephone) }}"
                               placeholder="+216 XX XXX XXX">
                        @error('telephone')<span class="form-error">{{ $message }}</span>@enderror
                    </label>

                    @if(auth()->user()->role === 'admin')
                    <label>Statut <span class="required">*</span>
                        <select name="statut" class="form-control" required>
                            @foreach(['en_attente' => 'En attente', 'actif' => 'Actif', 'suspendu' => 'Suspendu'] as $val => $lbl)
                                <option value="{{ $val }}"
                                    {{ old('statut', $association?->statut) === $val ? 'selected' : '' }}>
                                    {{ $lbl }}
                                </option>
                            @endforeach
                        </select>
                        @error('statut')<span class="form-error">{{ $message }}</span>@enderror
                    </label>
                    @endif

                    <label class="span-2">Adresse du point de collecte <span class="required">*</span>
                        <input type="text" name="adresse" class="form-control"
                               value="{{ old('adresse', $association?->adresse) }}"
                               placeholder="Ex : 12 Rue de la République, Tunis 1000" required maxlength="255">
                        @error('adresse')<span class="form-error">{{ $message }}</span>@enderror
                    </label>

                    <label class="span-2">Mission & description <span class="required">*</span>
                        <textarea name="description" class="form-control" rows="3"
                                  placeholder="Décrivez la mission, les actions et les valeurs de l'association..."
                                  required>{{ old('description', $association?->description) }}</textarea>
                        @error('description')<span class="form-error">{{ $message }}</span>@enderror
                    </label>
                </div>
            </fieldset>

            {{-- ── Section 2 : Besoins de collecte ── --}}
            <fieldset class="field" style="border:0;padding:0;margin:0 0 28px">
                <legend style="font-size:13px;font-weight:700;margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted)">
                    Besoins de collecte
                </legend>
                <p style="font-size:13px;color:var(--muted);margin:0 0 14px">
                    Définissez les types de textiles dont l'association a besoin.
                </p>

                {{-- Saisie en une phrase : l'IA propose des lignes, l'association relit puis enregistre --}}
                <div class="besoins-ia">
                    <label for="besoins-texte" class="besoins-ia__label">Ou décrivez vos besoins en une phrase</label>
                    <textarea id="besoins-texte" class="form-control besoins-ia__texte" rows="5" maxlength="1000"
                              placeholder="Ex : On cherche 30 manteaux pour enfants en taille L et 15 pulls pour adultes, toutes tailles."></textarea>
                    <div class="besoins-ia__actions">
                        <button type="button" class="btn btn-secondary small" id="besoins-ia-btn">
                            <i data-lucide="sparkles" aria-hidden="true"></i> Remplir avec l'IA
                        </button>
                        <span id="besoins-ia-message" class="besoins-ia__message" role="status" aria-live="polite"></span>
                    </div>
                </div>

                <div id="besoins-list" style="display:flex;flex-direction:column;gap:10px">
                    @php $besoins = old('besoins', $association?->besoins ?? []); @endphp
                    @forelse($besoins as $i => $besoin)
                        <div class="besoin-row" data-index="{{ $i }}">
                            <select name="besoins[{{ $i }}][type]" class="form-control" aria-label="Type de textile du besoin {{ $i + 1 }}">
                                <option value="">— Type de textile —</option>
                                @foreach($typesTextile as $t)
                                    <option value="{{ $t }}" {{ ($besoin['type'] ?? '') === $t ? 'selected' : '' }}>{{ $t }}</option>
                                @endforeach
                            </select>
                            <select name="besoins[{{ $i }}][taille]" class="form-control small-select" aria-label="Taille du besoin {{ $i + 1 }}">
                                <option value="">Taille</option>
                                @foreach($tailles as $ta)
                                    <option value="{{ $ta }}" {{ ($besoin['taille'] ?? '') === $ta ? 'selected' : '' }}>{{ $ta }}</option>
                                @endforeach
                            </select>
                            <input type="number" name="besoins[{{ $i }}][quantite]" class="form-control small-input"
                                   placeholder="Qté" min="1" value="{{ $besoin['quantite'] ?? '' }}" aria-label="Quantité du besoin {{ $i + 1 }}">
                            <button type="button" class="icon-btn danger remove-besoin" aria-label="Retirer le besoin {{ $i + 1 }}">
                                <i data-lucide="x" aria-hidden="true"></i>
                            </button>
                        </div>
                    @empty
                    @endforelse
                </div>

                <button type="button" class="btn btn-secondary small" id="add-besoin" style="margin-top:12px">
                    <i data-lucide="plus"></i> Ajouter un besoin
                </button>
            </fieldset>

            {{-- ── Actions ── --}}
            <div class="modal-actions">
                @unless($premiereFiche ?? false)
                    <a href="{{ route('back.associations') }}" class="btn btn-secondary">Annuler</a>
                @endunless
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="save"></i>
                    {{ $association ? 'Enregistrer les modifications' : (($premiereFiche ?? false) ? 'Créer mon association' : 'Créer l\'association') }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const typesOptions = @json($typesTextile);
const taillesOptions = @json($tailles);

function buildBesoinRow(index) {
    const typeOpts  = typesOptions.map(t  => `<option value="${t}">${t}</option>`).join('');
    const tailleOpts = taillesOptions.map(t => `<option value="${t}">${t}</option>`).join('');
    return `
    <div class="besoin-row" data-index="${index}">
        <select name="besoins[${index}][type]" class="form-control" aria-label="Type de textile du besoin ${index + 1}">
            <option value="">— Type de textile —</option>${typeOpts}
        </select>
        <select name="besoins[${index}][taille]" class="form-control small-select" aria-label="Taille du besoin ${index + 1}">
            <option value="">Taille</option>${tailleOpts}
        </select>
        <input type="number" name="besoins[${index}][quantite]" class="form-control small-input" placeholder="Qté" min="1" aria-label="Quantité du besoin ${index + 1}">
        <button type="button" class="icon-btn danger remove-besoin" aria-label="Retirer le besoin ${index + 1}"><i data-lucide="x" aria-hidden="true"></i></button>
    </div>`;
}

let nextIndex = document.querySelectorAll('.besoin-row').length;

document.getElementById('add-besoin').addEventListener('click', () => {
    document.getElementById('besoins-list').insertAdjacentHTML('beforeend', buildBesoinRow(nextIndex++));
    lucide.createIcons();
});

document.addEventListener('click', e => {
    if (e.target.closest('.remove-besoin')) {
        e.target.closest('.besoin-row').remove();
    }
});

// ── Besoins en texte libre : l'IA propose des lignes, rien n'est enregistré ──
const BESOINS_IA_URL = "{{ route('back.associations.besoins-ia') }}";

function remplirLigne(index, besoin) {
    const ligne = document.querySelector(`.besoin-row[data-index="${index}"]`);
    if (!ligne) return;
    ligne.querySelector('select[name$="[type]"]').value = besoin.type;
    ligne.querySelector('select[name$="[taille]"]').value = besoin.taille || '';
    ligne.querySelector('input[name$="[quantite]"]').value = besoin.quantite ?? '';
}

document.getElementById('besoins-ia-btn')?.addEventListener('click', async () => {
    const champ = document.getElementById('besoins-texte');
    const message = document.getElementById('besoins-ia-message');
    const bouton = document.getElementById('besoins-ia-btn');
    const texte = champ.value.trim();

    if (texte.length < 5) {
        message.textContent = 'Écrivez au moins une courte phrase.';
        return;
    }

    bouton.disabled = true;
    message.textContent = 'Analyse en cours…';

    try {
        const reponse = await fetch(BESOINS_IA_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    || document.querySelector('input[name="_token"]')?.value,
            },
            credentials: 'same-origin',
            body: JSON.stringify({ texte }),
        });
        if (!reponse.ok) throw new Error('HTTP ' + reponse.status);
        const data = await reponse.json();

        (data.besoins || []).forEach(besoin => {
            const index = nextIndex++;
            document.getElementById('besoins-list').insertAdjacentHTML('beforeend', buildBesoinRow(index));
            remplirLigne(index, besoin);
        });
        lucide.createIcons();

        message.textContent = data.message
            || (data.besoins.length + ' besoin(s) ajouté(s). Vérifiez-les puis enregistrez.');
    } catch (e) {
        message.textContent = "Impossible de contacter l'IA. Remplissez les besoins à la main.";
    } finally {
        bouton.disabled = false;
    }
});
</script>
@endsection