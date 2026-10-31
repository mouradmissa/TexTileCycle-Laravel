@extends('layouts.back')

@section('page-title', $pageTitle)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/module5-admin.css') }}">
@endpush

@php
    use App\Modules\Signalements\Models\Signalement;
@endphp

@section('content')
<div class="module">
    @include('back.partials.flash')

    <div class="module-toolbar">
        <div class="chips">
            <a href="{{ route('back.signalements.index', array_diff_key($filters, ['statut' => 1])) }}" @class(['chip', 'active' => empty($filters['statut'])])>
                Tous <b>{{ array_sum($counts) }}</b>
            </a>
            @foreach(Signalement::STATUT_LABELS as $statut => $libelle)
                <a href="{{ route('back.signalements.index', array_merge($filters, ['statut' => $statut])) }}" @class(['chip', 'active' => ($filters['statut'] ?? null) === $statut])>
                    {{ $libelle }} <b>{{ $counts[$statut] }}</b>
                </a>
            @endforeach
        </div>
        <a href="{{ route('back.signalements.create') }}" class="btn btn-primary small"><i data-lucide="plus"></i> Nouveau signalement</a>
    </div>

    <form method="get" class="inline-form panel" style="padding:14px 16px;margin-bottom:14px">
        @if(! empty($filters['statut']))
            <input type="hidden" name="statut" value="{{ $filters['statut'] }}">
        @endif
        <label>Type
            <select name="type">
                <option value="">Tous les types</option>
                @foreach(Signalement::TYPE_LABELS as $valeur => $libelle)
                    <option value="{{ $valeur }}" @selected(($filters['type'] ?? null) === $valeur)>{{ $libelle }}</option>
                @endforeach
            </select>
        </label>
        <label>Élément
            <select name="cible_type">
                <option value="">Tous les éléments</option>
                @foreach(Signalement::CIBLES as $alias => $config)
                    <option value="{{ $alias }}" @selected(($filters['cible_type'] ?? null) === $alias)>{{ $config['nom'] }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit" class="btn btn-secondary small"><i data-lucide="filter"></i> Filtrer</button>
        @if(array_filter($filters))
            <a href="{{ route('back.signalements.index') }}" class="link-button">Réinitialiser</a>
        @endif
    </form>

    <div class="panel table-panel">
        <table>
            <caption class="sr-only">Signalements</caption>
            <thead>
                <tr>
                    <th scope="col">Date</th>
                    <th scope="col">Élément signalé</th>
                    <th scope="col">Type</th>
                    <th scope="col">Motif</th>
                    <th scope="col">Auteur</th>
                    <th scope="col">Statut</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($signalements as $signalement)
                    <tr>
                        <td>{{ $signalement->created_at?->format('d/m/Y H:i') }}</td>
                        <td>
                            <b>{{ $signalement->cibleLabel() }}</b>
                            <span class="cell-sub">{{ $signalement->cibleNom() }}</span>
                        </td>
                        <td>{{ $signalement->typeLabel() }}</td>
                        <td class="motif">{{ \Illuminate\Support\Str::limit($signalement->motif, 90) }}</td>
                        <td>{{ $signalement->auteur?->name ?? 'Compte supprimé' }}</td>
                        <td><x-status-badge :tone="$signalement->statutTone()">{{ $signalement->statutLabel() }}</x-status-badge></td>
                        <td>
                            <div class="row-actions">
                                <a href="{{ route('back.signalements.show', $signalement->getKey()) }}" class="icon-btn" aria-label="Voir le signalement du {{ $signalement->created_at?->format('d/m/Y') }}"><i data-lucide="eye" aria-hidden="true"></i></a>
                                <a href="{{ route('back.signalements.edit', $signalement->getKey()) }}" class="icon-btn" aria-label="Modifier le signalement du {{ $signalement->created_at?->format('d/m/Y') }}"><i data-lucide="pencil" aria-hidden="true"></i></a>
                                <form method="post" action="{{ route('back.signalements.destroy', $signalement->getKey()) }}" onsubmit="return confirm('Supprimer définitivement ce signalement ?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="icon-btn btn-danger" aria-label="Supprimer le signalement du {{ $signalement->created_at?->format('d/m/Y') }}"><i data-lucide="trash-2" aria-hidden="true"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty">Aucun signalement pour ces critères.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($signalements->hasPages())
            <div style="padding:16px">{{ $signalements->links() }}</div>
        @endif
    </div>
</div>
@endsection
