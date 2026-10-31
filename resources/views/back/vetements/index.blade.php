@extends('layouts.back')

@section('page-title', $pageTitle)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/module5-admin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/ateliers-back.css') }}">
@endpush

@php
    $filtre = $statut ?? null;
    $items = $filtre
        ? $vetements->filter(fn ($v) => $v->status === $filtre)
        : $vetements;
@endphp

@section('content')
<div class="module">
    @include('back.partials.flash')

    @if($atelier)
        @include('back.ateliers.espace.partials.entete', [
            'onglet' => 'pieces',
            'nbServices' => $atelier->services->count(),
            'nbRdv' => null,
            'nbPieces' => $vetements->count(),
        ])
        <p class="muted" style="margin:0 0 16px">Uniquement les pièces envoyées à <b>{{ $atelier->nom }}</b> par les citoyens.</p>
    @endif

    <div class="module-toolbar">
        <div class="chips">
            <a href="{{ route('back.vetements') }}" @class(['chip', 'active' => ! $filtre])>
                Toutes <b>{{ $counts['total'] ?? $vetements->count() }}</b>
            </a>
            @foreach(['en_attente' => 'En attente', 'en_reparation' => 'En réparation', 'repare' => 'Réparées'] as $s => $label)
                <a href="{{ route('back.vetements', ['statut' => $s]) }}" @class(['chip', 'active' => $filtre === $s])>
                    {{ $label }} <b>{{ $counts[$s] ?? $vetements->where('status', $s)->count() }}</b>
                </a>
            @endforeach
        </div>
    </div>

    <div class="panel table-panel">
        <table>
            <caption class="sr-only">Vêtements</caption>
            <thead>
                <tr>
                    <th scope="col">Pièce</th>
                    <th scope="col">Client</th>
                    <th scope="col">Parcours</th>
                    <th scope="col">Statut</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $vetement)
                    <tr>
                        <td>
                            <b>{{ $vetement->displayName() }}</b>
                            <span class="cell-sub">{{ $vetement->material ?? '' }} {{ $vetement->condition_label ? '· '.$vetement->condition_label : '' }}</span>
                            @if($vetement->libelleVerdictAnalyse())
                                <span class="cell-sub">IA · {{ $vetement->libelleVerdictAnalyse() }}</span>
                            @endif
                        </td>
                        <td>{{ $vetement->owner?->name ?? $vetement->ownerShortName() }}</td>
                        <td><x-status-badge :tone="$vetement->intendedActionTone()">{{ $vetement->intendedActionLabel() }}</x-status-badge></td>
                        <td><x-status-badge :tone="$vetement->statusTone()">{{ $vetement->statusLabel() }}</x-status-badge></td>
                        <td>
                            <div class="row-actions">
                                @if($atelier)
                                    @foreach($vetement->traitementsAtelier() as $cible)
                                        <form method="post" action="{{ route('back.vetements.traiter', $vetement->getKey()) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="{{ $cible }}">
                                            <button type="submit" class="btn btn-primary small">
                                                {{ $cible === 'en_reparation' ? 'Confirmer le rendez-vous' : 'Marquer réparé' }}
                                            </button>
                                        </form>
                                    @endforeach
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="empty">
                            @if($atelier)
                                Aucune pièce à traiter pour votre atelier.
                            @else
                                Aucun vêtement enregistré pour le moment.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
