@extends('layouts.trame')

@section('title', 'Mes rendez-vous')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/client-trame.css') }}">
@endpush

@section('bandeau')
    <nav class="tr-ariane" aria-label="Fil d'Ariane">
        <ol>
            <li><a href="{{ route('front.home') }}">Accueil</a></li>
            <li><span aria-current="page">Rendez-vous</span></li>
        </ol>
    </nav>
    <div class="tr-bandeau__grille">
        <div class="tr-bandeau__principal">
            <h1>Mes rendez-vous</h1>
            <p class="tr-bandeau__intro">Consultez, modifiez ou annulez vos rendez-vous de réparation.</p>
        </div>
        <div class="tr-bandeau__cote">
            <a href="{{ route('front.rdv.create') }}" class="tr-pilule est-claire">
                <i data-lucide="plus" aria-hidden="true"></i> Prendre rendez-vous
            </a>
        </div>
    </div>
@endsection

@section('content')
<main class="container page-content client-page">

    @if($rendezVous->isNotEmpty())
        {{-- Tableau des rendez-vous --}}
        <div class="panel" style="padding:0;overflow-x:auto">
            <table class="data-table" style="width:100%;border-collapse:collapse">
                <thead>
                    <tr style="text-align:left;border-bottom:1px solid var(--border)">
                        <th style="padding:12px 16px;font-weight:600;font-size:.85rem;color:var(--muted)">Vêtement</th>
                        <th style="padding:12px 16px;font-weight:600;font-size:.85rem;color:var(--muted)">Atelier</th>
                        <th style="padding:12px 16px;font-weight:600;font-size:.85rem;color:var(--muted)">Service</th>
                        <th style="padding:12px 16px;font-weight:600;font-size:.85rem;color:var(--muted)">Date</th>
                        <th style="padding:12px 16px;font-weight:600;font-size:.85rem;color:var(--muted)">Durée</th>
                        <th style="padding:12px 16px;font-weight:600;font-size:.85rem;color:var(--muted)">Statut</th>
                        <th style="padding:12px 16px;font-weight:600;font-size:.85rem;color:var(--muted)">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rendezVous as $rdv)
                        <tr style="border-bottom:1px solid var(--border)">
                            {{-- Vêtement --}}
                            <td style="padding:12px 16px;font-size:.9rem">
                                {{ $rdv->vetement ? $rdv->vetement->displayName() : '—' }}
                            </td>

                            {{-- Atelier --}}
                            <td style="padding:12px 16px;font-size:.9rem">
                                {{ $rdv->atelierNom() }}
                            </td>

                            {{-- Service --}}
                            <td style="padding:12px 16px;font-size:.9rem">
                                {{ $rdv->serviceNom() }}
                            </td>

                            {{-- Date --}}
                            <td style="padding:12px 16px;font-size:.9rem">
                                {{ $rdv->date_rdv ? $rdv->date_rdv->format('d/m/Y à H\hi') : '—' }}
                            </td>

                            {{-- Durée --}}
                            <td style="padding:12px 16px;font-size:.9rem">
                                {{ $rdv->dureeFormatee() }}
                            </td>

                            {{-- Badge de statut coloré --}}
                            <td style="padding:12px 16px">
                                <x-status-badge :tone="$rdv->statutTone()">{{ $rdv->statutLabel() }}</x-status-badge>
                            </td>

                            {{-- Boutons d'action selon les règles métier --}}
                            <td style="padding:12px 16px">
                                <div style="display:flex;gap:6px;flex-wrap:wrap">
                                    {{-- Voir — toujours disponible --}}
                                    <a href="{{ route('front.rdv.show', $rdv->getKey()) }}" class="btn btn-secondary small" title="Voir">
                                        <i data-lucide="eye"></i>
                                    </a>

                                    {{-- Modifier — seulement si en_attente --}}
                                    @if($rdv->peutEtreModifie())
                                        <a href="{{ route('front.rdv.edit', $rdv->getKey()) }}" class="btn btn-secondary small" title="Modifier">
                                            <i data-lucide="pencil"></i>
                                        </a>
                                    @endif

                                    {{-- Annuler — seulement si en_attente ou confirme --}}
                                    @if($rdv->peutEtreAnnule())
                                        <form action="{{ route('front.rdv.annuler', $rdv->getKey()) }}" method="post"
                                              onsubmit="return confirm('Voulez-vous vraiment annuler ce rendez-vous ?')" style="display:inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-secondary small" title="Annuler" style="color:var(--red, #e53935)">
                                                <i data-lucide="x-circle"></i>
                                            </button>
                                        </form>
                                    @endif

                                    {{-- Supprimer — seulement si en_attente ou annule --}}
                                    @if($rdv->peutEtreSupprime())
                                        <form action="{{ route('front.rdv.destroy', $rdv->getKey()) }}" method="post"
                                              onsubmit="return confirm('Voulez-vous vraiment supprimer ce rendez-vous ?')" style="display:inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-secondary small" title="Supprimer" style="color:var(--red, #e53935)">
                                                <i data-lucide="trash-2"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div style="margin-top:16px;display:flex;justify-content:center">
            {{ $rendezVous->links('vendor.pagination.textilecycle') }}
        </div>
    @else
        {{-- Message si aucun rendez-vous --}}
        <section class="tr-fil vetements-vide" style="padding:8px 0 24px;text-align:left;color:var(--muted)">
            <i data-lucide="calendar-off" style="width:48px;height:48px;margin-bottom:12px;opacity:.4"></i>
            <p>Vous n'avez encore aucun rendez-vous.</p>
            <a href="{{ route('front.rdv.create') }}" class="btn btn-primary" style="margin-top:16px">
                <i data-lucide="plus"></i> Prendre un rendez-vous
            </a>
        </section>
    @endif
</main>
@endsection
