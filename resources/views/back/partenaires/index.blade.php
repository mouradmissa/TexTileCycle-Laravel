@extends('layouts.back')

@section('page-title', $pageTitle)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/module5-admin.css') }}">
@endpush

@php
    $statutLabels = ['en_attente' => 'En attente', 'actif' => 'Actif', 'suspendu' => 'Suspendu'];
    $statutTones = ['en_attente' => 'orange', 'actif' => 'green', 'suspendu' => 'purple'];
@endphp

@section('content')
<div class="module">
    @include('back.partials.flash')

    <nav class="tabs">
        @foreach($types as $key => $config)
            <a href="{{ route('back.partenaires.index', ['type' => $key]) }}" @class(['active' => $type === $key])>
                {{ $config['label'] }}s
            </a>
        @endforeach
    </nav>

    <p class="muted" style="margin:0 0 12px">Valider un partenaire active aussi le compte responsable. Suspendre ou remettre en attente le désactive : il ne pourra plus se connecter.</p>

    <div class="module-toolbar">
        <div class="chips">
            <a href="{{ route('back.partenaires.index', ['type' => $type, 'q' => $search]) }}" @class(['chip', 'active' => ! $statut])>
                Tous <b>{{ array_sum($counts) }}</b>
            </a>
            @foreach($statuts as $s)
                <a href="{{ route('back.partenaires.index', ['type' => $type, 'statut' => $s, 'q' => $search]) }}" @class(['chip', 'active' => $statut === $s])>
                    {{ $statutLabels[$s] }} <b>{{ $counts[$s] ?? 0 }}</b>
                </a>
            @endforeach
        </div>
        <form method="get" class="inline-form">
            <input type="hidden" name="type" value="{{ $type }}">
            <input type="hidden" name="statut" value="{{ $statut }}">
            <div class="search-box compact">
                <i data-lucide="search"></i>
                <input type="search" name="q" value="{{ $search }}" placeholder="Nom, adresse, ville…">
            </div>
        </form>
    </div>

    <div class="panel table-panel">
        <table>
            <caption class="sr-only">Partenaires</caption>
            <thead>
                <tr>
                    <th scope="col">{{ $types[$type]['label'] }}</th>
                    <th scope="col">Adresse</th>
                    <th scope="col">Compte responsable</th>
                    <th scope="col">Inscrit le</th>
                    <th scope="col">Statut</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($partenaires as $p)
                    <tr>
                        <td>
                            <b>{{ $p['nom'] }}</b>
                            @if($p['note'] !== null)
                                <span class="cell-sub">★ {{ number_format($p['note'], 1, ',', ' ') }} / 5</span>
                            @endif
                        </td>
                        <td>
                            {{ $p['adresse'] ?: '—' }}
                            @if($p['telephone'])
                                <span class="cell-sub">{{ $p['telephone'] }}</span>
                            @endif
                        </td>
                        <td>
                            @if($p['owner'])
                                {{ $p['owner']['name'] }}
                                <span class="cell-sub">{{ $p['owner']['email'] }}</span>
                            @else
                                <span class="cell-sub">Compte introuvable</span>
                            @endif
                        </td>
                        <td>{{ $p['created_at']?->format('d/m/Y') ?? '—' }}</td>
                        <td><x-status-badge :tone="$statutTones[$p['statut']] ?? 'orange'">{{ $statutLabels[$p['statut']] ?? $p['statut'] }}</x-status-badge></td>
                        <td>
                            <div class="row-actions">
                                @foreach([
                                    'actif' => ['Valider', 'check', 'btn-primary', $p['statut'] === 'en_attente' ? 'Valider' : 'Réactiver'],
                                    'suspendu' => ['Suspendre', 'ban', 'btn-secondary btn-danger', 'Suspendre'],
                                    'en_attente' => ['Remettre en attente', 'rotate-ccw', 'btn-secondary', 'En attente'],
                                ] as $cible => [$titre, $icone, $classe, $libelle])
                                    @continue($p['statut'] === $cible)
                                    <form method="post" action="{{ route('back.partenaires.statut', [$type, $p['id']]) }}"
                                          @if($cible === 'suspendu') onsubmit="return confirm('Suspendre {{ addslashes($p['nom']) }} ? Il ne sera plus visible par les citoyens.')" @endif>
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="statut" value="{{ $cible }}">
                                        <button type="submit" class="btn small {{ $classe }}" title="{{ $titre }}"><i data-lucide="{{ $icone }}"></i> {{ $libelle }}</button>
                                    </form>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="empty">Aucun partenaire pour ces critères.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
