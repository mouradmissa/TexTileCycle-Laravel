{{-- Partial formulaire partagé entre create et edit --}}
{{-- Variables attendues : $vetements, $ateliers, $services, $rendezVous (optionnel pour edit) --}}

@php
    $isEdit = isset($rendezVous);
@endphp

<form action="{{ $isEdit ? route('front.rdv.update', $rendezVous->getKey()) : route('front.rdv.store') }}" method="post">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="form-grid">
        {{-- Sélection du vêtement --}}
        <label>Vêtement
            @if($vetements->isEmpty())
                <div style="background:#fff3e0;border:1px solid #ffe0b2;padding:8px 12px;border-radius:6px;font-size:.85rem;color:#7a3e0c;margin-bottom:8px">
                    <i data-lucide="alert-circle" aria-hidden="true" style="width:14px;height:14px;vertical-align:middle"></i>
                    Vous n'avez pas encore de vêtement enregistré.
                    <a href="{{ route('front.vetements') }}" style="color:#7a3e0c;font-weight:600;text-decoration:underline">Créer un vêtement dans « Mes vêtements »</a>
                </div>
            @endif
            <select name="vetement_id" required>
                <option value="">Sélectionner un vêtement</option>
                @foreach($vetements as $vetement)
                    <option value="{{ $vetement->getKey() }}"
                        {{ old('vetement_id', $isEdit ? $rendezVous->vetement_id : ($vetementPreselect ?? '')) == $vetement->getKey() ? 'selected' : '' }}>
                        {{ $vetement->displayName() }}
                    </option>
                @endforeach
            </select>
            @error('vetement_id')
                <span class="form-error">{{ $message }}</span>
            @enderror
        </label>

        {{-- Sélection de l'atelier --}}
        <label>Atelier
            <select name="atelier_id" id="rdv-atelier" required>
                <option value="">Sélectionner</option>
                @foreach($ateliers as $id => $nom)
                    <option value="{{ $id }}"
                        {{ old('atelier_id', $isEdit ? $rendezVous->atelier_id : ($atelierPreselect ?? '')) == $id ? 'selected' : '' }}>
                        {{ $nom }}
                    </option>
                @endforeach
            </select>
            @error('atelier_id')
                <span class="form-error">{{ $message }}</span>
            @enderror
        </label>
    </div>

    <div class="form-grid">
        {{-- Sélection du service --}}
        <label>Service
            <select name="service_id" id="rdv-service" data-services-par-atelier='@json($servicesParAtelier ?? [])'>
                <option value="">Aucun (optionnel)</option>
                @foreach($services as $id => $nom)
                    <option value="{{ $id }}"
                        {{ old('service_id', $isEdit ? $rendezVous->service_id : ($servicePreselect ?? '')) == $id ? 'selected' : '' }}>
                        {{ $nom }}
                    </option>
                @endforeach
            </select>
            @error('service_id')
                <span class="form-error">{{ $message }}</span>
            @enderror
        </label>

        {{-- Date et heure --}}
        <label>Date et heure
            <input type="datetime-local" name="date_rdv" required
                   value="{{ old('date_rdv', $isEdit && $rendezVous->date_rdv ? $rendezVous->date_rdv->format('Y-m-d\TH:i') : '') }}">
            @error('date_rdv')
                <span class="form-error">{{ $message }}</span>
            @enderror
        </label>
    </div>

    <div class="form-grid">
        {{-- Durée estimée --}}
        <label>Durée estimée (minutes)
            <select name="duree" required>
                <option value="">Sélectionner</option>
                @foreach([15, 30, 45, 60, 90, 120, 180, 240, 360, 480] as $d)
                    <option value="{{ $d }}"
                        {{ old('duree', $isEdit ? $rendezVous->duree : '') == $d ? 'selected' : '' }}>
                        {{ $d >= 60 ? intdiv($d, 60) . 'h' . ($d % 60 > 0 ? $d % 60 . 'min' : '') : $d . ' min' }}
                    </option>
                @endforeach
            </select>
            @error('duree')
                <span class="form-error">{{ $message }}</span>
            @enderror
        </label>
        <div></div>
    </div>

    {{-- Commentaire --}}
    <label>Commentaire
        <textarea name="commentaire" placeholder="Précisez la nature de la réparation souhaitée...">{{ old('commentaire', $isEdit ? $rendezVous->commentaire : '') }}</textarea>
        @error('commentaire')
            <span class="form-error">{{ $message }}</span>
        @enderror
    </label>

    <div class="modal-actions">
        <a href="{{ route('front.rdv') }}" class="btn btn-secondary">Retour</a>
        <button type="submit" class="btn btn-primary">
            {{ $isEdit ? 'Mettre à jour' : 'Confirmer le RDV' }}
            <i data-lucide="{{ $isEdit ? 'check' : 'calendar-days' }}"></i>
        </button>
    </div>
</form>

@if(! empty($servicesParAtelier))
<script>
(function () {
    var atelier = document.getElementById('rdv-atelier');
    var service = document.getElementById('rdv-service');
    if (!atelier || !service) return;
    var map = {};
    try { map = JSON.parse(service.getAttribute('data-services-par-atelier') || '{}'); } catch (e) { return; }
    var selected = service.value;
    function fill(atelierId) {
        var options = map[atelierId] || {};
        service.innerHTML = '<option value="">Aucun (optionnel)</option>';
        Object.keys(options).forEach(function (id) {
            var opt = document.createElement('option');
            opt.value = id;
            opt.textContent = options[id];
            if (id === selected) opt.selected = true;
            service.appendChild(opt);
        });
    }
    atelier.addEventListener('change', function () { selected = ''; fill(this.value); });
    if (atelier.value) fill(atelier.value);
})();
</script>
@endif
