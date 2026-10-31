@extends('layouts.back')

@section('page-title', 'Associations')

@section('content')
<div class="module">

    @if(empty($espaceAssociation))
    {{-- Toolbar --}}
    <div class="module-toolbar">
        <form method="GET" class="filter-row" id="filter-form">
            <select name="statut" class="select-btn" onchange="this.form.submit()">
                <option value="">Tous les statuts</option>
                @foreach($statuts as $s)
                    <option value="{{ $s }}" {{ $statut === $s ? 'selected' : '' }}>
                        {{ match($s) { 'actif' => 'Actif', 'suspendu' => 'Suspendu', default => 'En attente' } }}
                    </option>
                @endforeach
            </select>
        </form>
        <a href="{{ route('back.associations.create') }}" class="btn btn-primary">
            <i data-lucide="plus"></i> Nouvelle association
        </a>
    </div>
    @endif

    {{-- Table --}}
    <div class="panel table-panel">
        <table>
            <caption class="sr-only">Associations</caption>
            <thead>
                <tr>
                    <th scope="col">Association</th>
                    <th scope="col">Adresse</th>
                    <th scope="col">Téléphone</th>
                    <th scope="col">Besoins</th>
                    <th scope="col">Statut</th>
                    <th scope="col">Créée le</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($associations as $asso)
                    <tr>
                        <td>
                            <div class="table-item">
                                <div class="table-thumb" style="background:var(--green-50);color:var(--green)">
                                    <i data-lucide="heart-handshake"></i>
                                </div>
                                <div>
                                    <b>{{ $asso->nom }}</b>
                                    <small class="muted">{{ Str::limit($asso->description, 60) }}</small>
                                </div>
                            </div>
                        </td>
                        <td class="muted small">{{ $asso->adresse }}</td>
                        <td class="muted small">{{ $asso->telephone ?: '—' }}</td>
                        <td>
                            @if(!empty($asso->besoins))
                                <div class="besoins-tags compact">
                                    @foreach(array_slice($asso->besoins, 0, 2) as $b)
                                        <span class="tag small">{{ $b['type'] ?? '?' }}</span>
                                    @endforeach
                                    @if(count($asso->besoins) > 2)
                                        <span class="tag small muted">+{{ count($asso->besoins) - 2 }}</span>
                                    @endif
                                </div>
                            @else
                                <span class="muted small">Aucun</span>
                            @endif
                        </td>
                        <td>
                            <x-status-badge :tone="$asso->statutTone()">
                                {{ $asso->statutLabel() }}
                            </x-status-badge>
                        </td>
                        <td class="muted small">{{ $asso->created_at->format('d/m/Y') }}</td>
                        <td>
                            <div class="action-row">
                                <a href="{{ route('back.associations.edit', $asso->_id) }}"
                                   class="icon-btn" aria-label="Modifier {{ $asso->nom }}">
                                    <i data-lucide="pencil" aria-hidden="true"></i>
                                </a>
                                @if(auth()->user()?->isAdmin())
                                <form method="POST"
                                      action="{{ route('back.associations.destroy', $asso->_id) }}"
                                      onsubmit="return confirm('Supprimer « {{ $asso->nom }} » ?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="icon-btn danger" aria-label="Supprimer {{ $asso->nom }}">
                                        <i data-lucide="trash-2" aria-hidden="true"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty-cell">
                            <div class="empty-state compact">
                                <i data-lucide="heart-handshake"></i>
                                <p>Aucune association enregistrée.</p>
                                @if(empty($espaceAssociation))
                                <a href="{{ route('back.associations.create') }}" class="btn btn-primary small">
                                    Ajouter la première
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
