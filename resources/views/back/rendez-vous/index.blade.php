@extends('layouts.back')

@section('page-title', $pageTitle)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/module5-admin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/ateliers-back.css') }}">
@endpush

@php
    $statutLabels = [
        'en_attente' => 'En attente',
        'confirme' => 'Confirmé',
        'annule' => 'Annulé',
        'termine' => 'Terminé',
    ];
    $items = $statut
        ? $rendezVous->filter(fn ($rdv) => $rdv->statut === $statut)
        : $rendezVous;
@endphp

@section('content')
<div class="module">
    @include('back.partials.flash')

    @if($atelier)
        @include('back.ateliers.espace.partials.entete', [
            'onglet' => 'rdv',
            'nbServices' => $atelier->services->count(),
            'nbRdv' => $counts['total'] ?? $rendezVous->count(),
            'nbPieces' => null,
        ])
    @endif

    <div class="module-toolbar">
        <div class="chips">
            <a href="{{ route('back.rdv') }}" @class(['chip', 'active' => ! $statut])>
                Tous <b>{{ $counts['total'] ?? $rendezVous->count() }}</b>
            </a>
            @foreach($statuts as $s)
                <a href="{{ route('back.rdv', ['statut' => $s]) }}" @class(['chip', 'active' => $statut === $s])>
                    {{ $statutLabels[$s] ?? $s }} <b>{{ $counts[$s] ?? 0 }}</b>
                </a>
            @endforeach
        </div>
    </div>

    <div class="panel table-panel">
        <table>
            <caption class="sr-only">Rendez-vous</caption>
            <thead>
                <tr>
                    <th scope="col">Date</th>
                    <th scope="col">Vêtement</th>
                    <th scope="col">Client</th>
                    @unless($atelier)<th scope="col">Atelier</th>@endunless
                    <th scope="col">Service</th>
                    <th scope="col">Statut</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $rdv)
                    <tr>
                        <td>
                            <b>{{ $rdv->date_rdv?->format('d/m/Y H:i') ?? '—' }}</b>
                            <span class="cell-sub">{{ $rdv->dureeFormatee() }}</span>
                        </td>
                        <td>
                            <b>{{ $rdv->vetement?->displayName() ?? '—' }}</b>
                            @if($rdv->commentaire)
                                <span class="cell-sub">{{ \Illuminate\Support\Str::limit($rdv->commentaire, 80) }}</span>
                            @endif
                        </td>
                        <td>{{ $rdv->user?->name ?? '—' }}</td>
                        @unless($atelier)
                            <td>{{ $rdv->atelierNom() }}</td>
                        @endunless
                        <td>{{ $rdv->serviceNom() }}</td>
                        <td><x-status-badge :tone="$rdv->statutTone()">{{ $rdv->statutLabel() }}</x-status-badge></td>
                        <td>
                            <div class="row-actions">
                                @foreach($rdv->transitionsAtelier() as $cible)
                                    <form method="post" action="{{ route('back.rdv.statut', $rdv->getKey()) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="statut" value="{{ $cible }}">
                                        <button type="submit" class="btn small {{ $cible === 'annule' ? 'btn-secondary btn-danger' : 'btn-primary' }}">
                                            {{ $cible === 'confirme' ? 'Confirmer' : ($cible === 'termine' ? 'Terminer' : 'Annuler') }}
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $atelier ? 6 : 7 }}" class="empty">
                            @if($atelier)
                                Aucun rendez-vous pour votre atelier pour le moment.
                            @else
                                Aucun rendez-vous enregistré.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
