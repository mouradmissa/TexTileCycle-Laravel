@extends('layouts.trame')

@section('title', 'Détail du rendez-vous')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/client-trame.css') }}">
@endpush

@section('bandeau')
    <nav class="tr-ariane" aria-label="Fil d'Ariane">
        <ol>
            <li><a href="{{ route('front.home') }}">Accueil</a></li>
            <li><a href="{{ route('front.rdv') }}">Rendez-vous</a></li>
            <li><span aria-current="page">Détail</span></li>
        </ol>
    </nav>
    <div class="tr-bandeau__grille">
        <div class="tr-bandeau__principal">
            <h1>Rendez-vous du {{ $rendezVous->date_rdv ? $rendezVous->date_rdv->format('d/m/Y') : '—' }}</h1>
            <p class="tr-bandeau__intro">Consultez les informations de votre rendez-vous.</p>
        </div>
        <div class="tr-bandeau__cote">
            <a href="{{ route('front.rdv') }}" class="tr-pilule est-claire">
                <i data-lucide="arrow-left" aria-hidden="true"></i> Retour à la liste
            </a>
        </div>
    </div>
@endsection

@section('content')
<main class="container page-content client-page">

    {{-- Détail complet --}}
    <div class="panel" style="padding:24px;max-width:720px">
        {{-- Statut en évidence --}}
        <div style="margin-bottom:20px;display:flex;align-items:center;gap:12px">
            <span style="font-weight:600;font-size:.9rem;color:var(--muted)">Statut :</span>
            <x-status-badge :tone="$rendezVous->statutTone()">{{ $rendezVous->statutLabel() }}</x-status-badge>
        </div>

        {{-- Informations détaillées --}}
        <div class="form-grid" style="gap:16px">
            <div>
                <span style="font-size:.8rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.03em">Vêtement</span>
                <p style="margin-top:4px;font-size:.95rem;font-weight:500">
                    {{ $rendezVous->vetement ? $rendezVous->vetement->displayName() : '—' }}
                </p>
            </div>
            <div>
                <span style="font-size:.8rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.03em">Atelier</span>
                <p style="margin-top:4px;font-size:.95rem;font-weight:500">
                    {{ $rendezVous->atelierNom() }}
                </p>
            </div>
        </div>

        <div class="form-grid" style="gap:16px;margin-top:16px">
            <div>
                <span style="font-size:.8rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.03em">Service</span>
                <p style="margin-top:4px;font-size:.95rem;font-weight:500">
                    {{ $rendezVous->serviceNom() }}
                </p>
            </div>
            <div>
                <span style="font-size:.8rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.03em">Date et heure</span>
                <p style="margin-top:4px;font-size:.95rem;font-weight:500">
                    {{ $rendezVous->date_rdv ? $rendezVous->date_rdv->format('d/m/Y à H\hi') : '—' }}
                </p>
            </div>
        </div>

        <div class="form-grid" style="gap:16px;margin-top:16px">
            <div>
                <span style="font-size:.8rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.03em">Durée estimée</span>
                <p style="margin-top:4px;font-size:.95rem;font-weight:500">
                    {{ $rendezVous->dureeFormatee() }}
                </p>
            </div>
            <div>
                <span style="font-size:.8rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.03em">Créé le</span>
                <p style="margin-top:4px;font-size:.95rem;font-weight:500">
                    {{ $rendezVous->created_at ? $rendezVous->created_at->format('d/m/Y à H\hi') : '—' }}
                </p>
            </div>
        </div>

        @if($rendezVous->commentaire)
            <div style="margin-top:16px">
                <span style="font-size:.8rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.03em">Commentaire</span>
                <p style="margin-top:4px;font-size:.95rem;background:var(--bg-alt, #f5f5f5);padding:12px 16px;border-radius:8px;line-height:1.5">
                    {{ $rendezVous->commentaire }}
                </p>
            </div>
        @endif

        {{-- Boutons d'action selon le statut --}}
        <div class="modal-actions" style="margin-top:24px">
            {{-- Modifier — seulement si en_attente --}}
            @if($rendezVous->peutEtreModifie())
                <a href="{{ route('front.rdv.edit', $rendezVous->getKey()) }}" class="btn btn-primary">
                    <i data-lucide="pencil"></i> Modifier
                </a>
            @endif

            {{-- Annuler — seulement si en_attente ou confirme --}}
            @if($rendezVous->peutEtreAnnule())
                <form action="{{ route('front.rdv.annuler', $rendezVous->getKey()) }}" method="post"
                      onsubmit="return confirm('Voulez-vous vraiment annuler ce rendez-vous ?')" style="display:inline">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-secondary" style="color:var(--red, #e53935)">
                        <i data-lucide="x-circle"></i> Annuler le RDV
                    </button>
                </form>
            @endif

            {{-- Supprimer — seulement si en_attente ou annule --}}
            @if($rendezVous->peutEtreSupprime())
                <form action="{{ route('front.rdv.destroy', $rendezVous->getKey()) }}" method="post"
                      onsubmit="return confirm('Voulez-vous vraiment supprimer ce rendez-vous ?')" style="display:inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-secondary" style="color:var(--red, #e53935)">
                        <i data-lucide="trash-2"></i> Supprimer
                    </button>
                </form>
            @endif
        </div>
    </div>
</main>
@endsection
