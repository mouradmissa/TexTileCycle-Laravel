@extends('layouts.back')

@section('page-title', $pageTitle)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/ateliers-back.css') }}">
@endpush

@section('content')
<div class="ab-page">
    @include('back.ateliers.espace.partials.entete', ['onglet' => 'passeports'])
    @include('back.ateliers.partials.flash')

    <section class="panel ab-passeports-intro" aria-labelledby="ab-passeports-intro-titre">
        <div class="ab-passeports-intro__icone" aria-hidden="true"><i data-lucide="qr-code"></i></div>
        <div>
            <h2 id="ab-passeports-intro-titre">Passeports de réparation</h2>
            <p>Certifiez chaque réparation terminée : imprimez l'étiquette QR et cousez-la sur la pièce. En la scannant,
               n'importe qui voit l'historique des réparations (sans aucune information sur votre client).</p>
        </div>
        <p class="ab-passeports-intro__compte"><strong>{{ $nbCertifiees }}</strong> / {{ $reparations->count() }} certifiées</p>
    </section>

    @if ($reparations->isEmpty())
        <section class="panel ab-empty" aria-labelledby="ab-passeports-vide">
            <div class="ab-empty__icon" aria-hidden="true"><i data-lucide="badge-check"></i></div>
            <h2 id="ab-passeports-vide">Aucune réparation terminée pour l'instant</h2>
            <p>Quand vous passez un rendez-vous à « Terminé », la réparation apparaît ici et vous pouvez générer son passeport.</p>
            <a href="{{ route('back.rdv') }}" class="btn btn-secondary">
                <i data-lucide="calendar-days" aria-hidden="true"></i> Voir mes rendez-vous
            </a>
        </section>
    @else
        <div class="panel table-panel ab-table-panel">
            <table class="ab-table">
                <caption class="sr-only">Réparations terminées et leurs passeports</caption>
                <thead>
                    <tr>
                        <th scope="col">Pièce</th>
                        <th scope="col">Client</th>
                        <th scope="col">Réparation</th>
                        <th scope="col">Date</th>
                        <th scope="col">Passeport</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($reparations as $ligne)
                        @php
                            $rdv = $ligne['rdv'];
                            $passeport = $ligne['passeport'];
                        @endphp
                        <tr>
                            <td data-label="Pièce" class="ab-cell-primary"><b>{{ $rdv->vetement?->displayName() ?? 'Vêtement' }}</b></td>
                            <td data-label="Client">{{ $rdv->user?->name ?? '—' }}</td>
                            <td data-label="Réparation">{{ $rdv->serviceNom() }}</td>
                            <td data-label="Date">{{ $rdv->date_rdv?->format('d/m/Y') ?? '—' }}</td>
                            <td data-label="Passeport">
                                @if (! $passeport)
                                    <x-status-badge tone="gray">Non généré</x-status-badge>
                                @elseif ($passeport->estRevoque())
                                    <x-status-badge tone="red">Révoqué</x-status-badge>
                                @else
                                    <x-status-badge tone="green">{{ $passeport->numeroCourt() }}</x-status-badge>
                                    @if ($passeport->garantieJours() > 0)
                                        <span class="ab-passeport-garantie">
                                            Garantie {{ $passeport->garantieActive() ? "jusqu'au ".$passeport->garantieFin()->format('d/m/Y') : 'expirée' }}
                                        </span>
                                    @endif
                                @endif
                            </td>
                            <td data-label="Actions" class="ab-actions-cell">
                                <div class="ab-actions">
                                    @if ($passeport)
                                        <a href="{{ route('back.ateliers.passeports.etiquette', ['code' => $passeport->code]) }}" class="btn btn-secondary small" target="_blank" rel="noopener">
                                            <i data-lucide="printer" aria-hidden="true"></i> Étiquette
                                            <span class="sr-only">(nouvel onglet)</span>
                                        </a>
                                        <a href="{{ route('front.passeport', ['code' => $passeport->code]) }}" class="icon-btn" target="_blank" rel="noopener"
                                           title="Voir la page publique" aria-label="Voir la page publique du passeport {{ $passeport->numeroCourt() }} (nouvel onglet)">
                                            <i data-lucide="external-link" aria-hidden="true"></i>
                                        </a>
                                    @elseif (\App\Modules\Ateliers\Services\PasseportService::estAVenir($rdv))
                                        {{-- RDV marqué « terminé » mais daté d'un jour futur : pas de certificat avant la date. --}}
                                        <span class="ab-passeport-avenir">
                                            <i data-lucide="calendar-clock" aria-hidden="true"></i> Possible le {{ $rdv->date_rdv->format('d/m/Y') }}
                                        </span>
                                    @else
                                        @php
                                            $rdvId = (string) $rdv->getKey();
                                            $ligneEnErreur = old('rendez_vous_id') === $rdvId;
                                        @endphp
                                        {{-- Formulaire replié : rouvert automatiquement s'il revient avec une erreur. --}}
                                        <details class="ab-generer" @if ($ligneEnErreur) open @endif>
                                            <summary class="btn btn-primary small">
                                                <i data-lucide="qr-code" aria-hidden="true"></i> Générer
                                            </summary>
                                            <form method="post" action="{{ route('back.ateliers.passeports.store') }}" class="ab-generer__form">
                                                @csrf
                                                <input type="hidden" name="rendez_vous_id" value="{{ $rdvId }}">

                                                <label for="mot-{{ $rdvId }}">Mot sur la réparation <span class="ab-muted">(facultatif, public)</span></label>
                                                <textarea id="mot-{{ $rdvId }}" name="mot_atelier" rows="2"
                                                          placeholder="Ex. : fermeture YKK remplacée, coutures renforcées">{{ $ligneEnErreur ? old('mot_atelier') : '' }}</textarea>

                                                <label for="garantie-{{ $rdvId }}">Garantie</label>
                                                <select id="garantie-{{ $rdvId }}" name="garantie_jours" class="ab-select">
                                                    <option value="0">Sans garantie</option>
                                                    @foreach (\App\Modules\Ateliers\Models\Passeport::GARANTIES as $jours)
                                                        <option value="{{ $jours }}" @selected($ligneEnErreur && (int) old('garantie_jours') === $jours)>{{ $jours }} jours</option>
                                                    @endforeach
                                                </select>

                                                <button type="submit" class="btn btn-primary small">Créer le passeport</button>
                                            </form>
                                        </details>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- RDV confirmés : la pièce est chez l'atelier mais la réparation n'est pas terminée, pas encore de passeport. --}}
    @if ($enCours->isNotEmpty())
        <section class="panel table-panel ab-table-panel" aria-labelledby="ab-en-cours-titre">
            <div class="panel-head">
                <div>
                    <h2 id="ab-en-cours-titre">Réparations en cours ({{ $enCours->count() }})</h2>
                    <p>Terminez le rendez-vous une fois la pièce réparée : vous pourrez alors générer son passeport.</p>
                </div>
                <a href="{{ route('back.rdv', ['statut' => \App\Modules\RendezVous\Models\RendezVous::STATUT_CONFIRME]) }}" class="btn btn-secondary small">
                    <i data-lucide="calendar-check" aria-hidden="true"></i> Terminer dans Rendez-vous
                </a>
            </div>
            <table class="ab-table">
                <caption class="sr-only">Réparations confirmées, pas encore terminées</caption>
                <thead>
                    <tr>
                        <th scope="col">Pièce</th>
                        <th scope="col">Client</th>
                        <th scope="col">Réparation</th>
                        <th scope="col">Rendez-vous</th>
                        <th scope="col">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($enCours as $rdv)
                        <tr>
                            <td data-label="Pièce" class="ab-cell-primary"><b>{{ $rdv->vetement?->displayName() ?? 'Vêtement' }}</b></td>
                            <td data-label="Client">{{ $rdv->user?->name ?? '—' }}</td>
                            <td data-label="Réparation">{{ $rdv->serviceNom() }}</td>
                            <td data-label="Rendez-vous">{{ $rdv->date_rdv?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td data-label="Statut"><x-status-badge tone="orange">En réparation</x-status-badge></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif
</div>
@endsection
