@extends('layouts.trame')

@section('title', 'Mes signalements')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/client-trame.css') }}">
@endpush

@section('bandeau')
    <nav class="tr-ariane" aria-label="Fil d'Ariane">
        <ol>
            <li><a href="{{ route('front.home') }}">Accueil</a></li>
            <li><span aria-current="page">Mes signalements</span></li>
        </ol>
    </nav>
    <div class="tr-bandeau__grille">
        <div class="tr-bandeau__principal">
            <h1>Mes signalements</h1>
            <p class="tr-bandeau__intro">Suivez le traitement des contenus que vous avez signalés à l'équipe TexTileCycle.</p>
        </div>
    </div>
@endsection

@section('content')
<main class="page-content container client-page">

    <div class="panel table-panel" style="margin-top:20px">
        <table>
            <caption class="sr-only">Mes signalements</caption>
            <thead>
                <tr>
                    <th scope="col">Date</th>
                    <th scope="col">Élément signalé</th>
                    <th scope="col">Motif</th>
                    <th scope="col">Statut</th>
                    <th scope="col">Réponse de l'équipe</th>
                </tr>
            </thead>
            <tbody>
                @forelse($signalements as $signalement)
                    <tr>
                        <td>{{ $signalement->created_at?->format('d/m/Y') }}</td>
                        <td>
                            <b>{{ $signalement->cibleLabel() }}</b>
                            <span style="display:block;color:var(--muted);font-size:11px">{{ $signalement->cibleNom() }}</span>
                        </td>
                        <td style="max-width:340px;white-space:normal">{{ \Illuminate\Support\Str::limit($signalement->motif, 120) }}</td>
                        <td><x-status-badge :tone="$signalement->statutTone()">{{ $signalement->statutLabel() }}</x-status-badge></td>
                        <td style="max-width:260px;white-space:normal">{{ $signalement->estEnAttente() ? '—' : ($signalement->note_admin ?: 'Traité par l\'équipe.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align:center;color:var(--muted);padding:40px">
                            Vous n'avez encore rien signalé. Un contenu vous semble inapproprié ? Utilisez le bouton « Signaler » sur la fiche concernée.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($signalements->hasPages())
            <div style="padding:16px">{{ $signalements->links() }}</div>
        @endif
    </div>
</main>
@endsection
