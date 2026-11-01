@extends('layouts.trame')

@section('title', 'Passeport de réparation '.$passeport->numeroCourt())

@push('styles')
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="{{ asset('css/passeport.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/passeport.js') }}" defer></script>
@endpush

@php
    $revoque = $passeport->estRevoque();
    $matiere = $passeport->info('matiere');
    $taille = $passeport->info('taille');
    $url = route('front.passeport', ['code' => $passeport->code]);

    // Résumé de la pièce : la garantie encore valable la plus longue, et l'atelier qui la couvre (s'il est encore actif).
    $nbReparations = $historique->count();
    $premiere = $historique->first();
    $derniere = $historique->last();
    $garantie = $historique->filter(fn ($p) => $p->garantieActive())->sortByDesc(fn ($p) => $p->garantieFin())->first();
    $atelierGarantie = $garantie ? $ateliers->get((string) $garantie->atelier_id) : null;
@endphp

{{-- Page publique ouverte par le QR code : aucune donnée sur le client, seulement la pièce et ses réparations. --}}
@section('bandeau')
    <nav class="tr-ariane" aria-label="Fil d'Ariane">
        <ol>
            <li><a href="{{ route('front.home') }}">Accueil</a></li>
            <li><a href="{{ route('front.passeport.verifier') }}">Passeports</a></li>
            <li><span aria-current="page">{{ $passeport->numeroCourt() }}</span></li>
        </ol>
    </nav>
    <div class="tr-bandeau__grille">
        <div class="tr-bandeau__principal">
            <p class="pp-kicker">Passeport de réparation · n° {{ $passeport->numeroCourt() }}</p>
            <h1>{{ $revoque ? 'Passeport révoqué' : 'Pièce réparée et certifiée' }}</h1>
            <p class="tr-bandeau__intro">
                {{ $passeport->vetementAffiche() }}@if ($matiere !== '') · {{ $matiere }}@endif
            </p>
        </div>
        <div class="tr-bandeau__cote">
            <p @class(['pp-sceau', 'is-revoque' => $revoque])>
                <i data-lucide="{{ $revoque ? 'shield-x' : 'shield-check' }}" aria-hidden="true"></i>
                {{ $revoque ? 'Non valide' : 'Certifié par un atelier partenaire' }}
            </p>
        </div>
    </div>
@endsection

@section('content')
<main class="container page-content pp">
    @if ($revoque)
        <section class="pp-revoque tr-fil" role="alert" aria-labelledby="pp-revoque-titre">
            <h2 id="pp-revoque-titre"><i data-lucide="shield-x" aria-hidden="true"></i> Ce passeport n'est plus valide</h2>
            <p>TexTileCycle a révoqué ce passeport le {{ $passeport->revoque_le->locale('fr')->isoFormat('D MMMM YYYY') }}.
               Les informations de réparation ne sont plus garanties. En cas de doute, contactez la plateforme.</p>
            <p><a href="{{ route('front.passeport.verifier') }}">Vérifier un autre passeport</a></p>
        </section>
    @else
        {{-- Carte d'identité de la pièce : l'essentiel en un coup d'œil, avant le détail des réparations. --}}
        <section class="pp-fiche" aria-labelledby="pp-fiche-titre">
            <h2 id="pp-fiche-titre" class="tr-sr">Résumé de la pièce</h2>
            <dl class="pp-fiche__grille">
                <div class="pp-fiche__case">
                    <dt><i data-lucide="shirt" aria-hidden="true"></i> Pièce</dt>
                    <dd>{{ $passeport->info('vetement') !== '' ? $passeport->info('vetement') : 'Vêtement' }}</dd>
                    <dd class="pp-fiche__detail">
                        {{ collect([$taille !== '' ? 'Taille '.$taille : null, $matiere !== '' ? $matiere : null])->filter()->implode(' · ') ?: 'Taille et matière non précisées' }}
                    </dd>
                </div>
                <div class="pp-fiche__case">
                    <dt><i data-lucide="scissors" aria-hidden="true"></i> Réparations certifiées</dt>
                    <dd>{{ $nbReparations }}</dd>
                    <dd class="pp-fiche__detail">
                        @if ($nbReparations > 1)
                            Depuis le {{ $premiere->dateReparationAffichee() }}
                        @else
                            Le {{ $passeport->dateReparationAffichee() }}
                        @endif
                    </dd>
                </div>
                <div class="pp-fiche__case">
                    <dt><i data-lucide="store" aria-hidden="true"></i> Dernier atelier</dt>
                    <dd>{{ $derniere->info('atelier') !== '' ? $derniere->info('atelier') : 'Atelier partenaire' }}</dd>
                    <dd class="pp-fiche__detail">{{ $derniere->info('ville') !== '' ? $derniere->info('ville') : 'Tunisie' }}</dd>
                </div>
                <div @class(['pp-fiche__case', 'is-garantie' => $garantie])>
                    <dt><i data-lucide="shield-check" aria-hidden="true"></i> Garantie</dt>
                    @if ($garantie)
                        <dd>Valable</dd>
                        <dd class="pp-fiche__detail">Jusqu'au {{ $garantie->garantieFin()->locale('fr')->isoFormat('D MMMM YYYY') }}</dd>
                    @else
                        <dd>Aucune en cours</dd>
                        <dd class="pp-fiche__detail">Voir le détail de chaque réparation</dd>
                    @endif
                </div>
            </dl>
        </section>

        {{-- Garantie encore valable : le propriétaire peut demander un rendez-vous au même atelier. --}}
        @if ($garantie && $atelierGarantie)
            <section class="pp-reclamer tr-fil" aria-labelledby="pp-reclamer-titre">
                <div>
                    <h2 id="pp-reclamer-titre">Un souci avec cette réparation ?</h2>
                    <p>{{ $atelierGarantie->nom }} la garantit jusqu'au {{ $garantie->garantieFin()->locale('fr')->isoFormat('D MMMM YYYY') }}.
                       Prenez rendez-vous et indiquez le passeport n° <strong>{{ $garantie->numeroCourt() }}</strong> dans votre demande.</p>
                </div>
                <a href="{{ route('front.rdv.create', ['atelier_id' => (string) $atelierGarantie->getKey()]) }}" class="btn btn-primary">
                    <i data-lucide="calendar-plus" aria-hidden="true"></i> Faire jouer la garantie
                </a>
            </section>
        @endif

        <div class="pp-layout">
            <section class="pp-historique tr-fil" aria-labelledby="pp-historique-titre">
                <h2 id="pp-historique-titre">Historique des réparations</h2>
                <p class="muted">{{ $nbReparations }} réparation{{ $nbReparations > 1 ? 's' : '' }} certifiée{{ $nbReparations > 1 ? 's' : '' }} pour cette pièce, de la plus ancienne à la plus récente.</p>

                <ol class="pp-frise">
                    @foreach ($historique as $etape)
                        @php
                            $actuel = (string) $etape->getKey() === (string) $passeport->getKey();
                            $atelierEtape = $ateliers->get((string) $etape->atelier_id);
                        @endphp
                        <li @class(['pp-frise__etape', 'is-actuel' => $actuel])>
                            <span class="pp-frise__point" aria-hidden="true"><i data-lucide="scissors"></i></span>
                            <article class="pp-frise__corps">
                                <header class="pp-frise__tete">
                                    <p class="pp-frise__date">
                                        <time datetime="{{ $etape->info('repare_le') }}">{{ $etape->dateReparationAffichee() }}</time>
                                        · n° {{ $etape->numeroCourt() }}
                                    </p>
                                    @if ($actuel)
                                        <span class="pp-frise__badge">Ce passeport</span>
                                    @endif
                                </header>
                                <h3>{{ $etape->reparationAffichee() }}</h3>

                                @if ($etape->info('service_description') !== '' || $etape->dureeServiceAffichee() !== '')
                                    <p class="pp-frise__service">
                                        {{ $etape->info('service_description') }}
                                        @if ($etape->dureeServiceAffichee() !== '')
                                            <span class="pp-frise__duree"><i data-lucide="clock" aria-hidden="true"></i> {{ $etape->dureeServiceAffichee() }} de travail</span>
                                        @endif
                                    </p>
                                @endif

                                @if ($etape->info('mot_atelier') !== '')
                                    <blockquote class="pp-frise__mot">
                                        <p>{{ $etape->info('mot_atelier') }}</p>
                                        <footer>— {{ $etape->info('atelier') !== '' ? $etape->info('atelier') : 'l\'atelier' }}</footer>
                                    </blockquote>
                                @endif

                                <p class="pp-frise__atelier">
                                    <i data-lucide="store" aria-hidden="true"></i>
                                    @if ($atelierEtape)
                                        <a href="{{ route('front.ateliers.show', ['id' => (string) $etape->atelier_id]) }}">{{ $etape->info('atelier') }}</a>
                                    @else
                                        <span>{{ $etape->info('atelier') }}</span>
                                    @endif
                                    @if ($etape->info('ville') !== '') · {{ $etape->info('ville') }} @endif
                                    @if ($atelierEtape && (int) $atelierEtape->nb_avis > 0)
                                        <span class="pp-frise__note">
                                            <i data-lucide="star" aria-hidden="true"></i>
                                            {{ $atelierEtape->noteFormatee() }}<span class="tr-sr"> sur 5</span> · {{ (int) $atelierEtape->nb_avis }} avis
                                        </span>
                                    @endif
                                </p>

                                @if ($etape->garantieJours() > 0)
                                    <p @class(['pp-frise__garantie', 'is-active' => $etape->garantieActive()])>
                                        <i data-lucide="{{ $etape->garantieActive() ? 'shield-check' : 'shield-off' }}" aria-hidden="true"></i>
                                        Garantie {{ $etape->garantieJours() }} jours ·
                                        {{ $etape->garantieActive() ? 'valable jusqu\'au' : 'expirée depuis le' }}
                                        {{ $etape->garantieFin()->locale('fr')->isoFormat('D MMMM YYYY') }}
                                    </p>
                                @endif
                            </article>
                        </li>
                    @endforeach
                </ol>
            </section>

            <aside class="pp-cote" aria-label="À propos de ce passeport">
                <section class="pp-partage tr-fil" aria-labelledby="pp-partage-titre" data-pp-bloc>
                    <h2 id="pp-partage-titre">Partager ce passeport</h2>
                    <p class="muted">Pour revendre ou donner la pièce : l'acheteur voit qu'elle a été réparée par un professionnel.</p>
                    <img class="pp-partage__qr" src="{{ route('front.passeport.qr', ['code' => $passeport->code]) }}" alt="QR code de ce passeport" width="140" height="140">

                    <label class="pp-partage__label" for="pp-lien">Adresse du passeport</label>
                    <input id="pp-lien" class="pp-partage__lien" type="text" value="{{ $url }}" readonly data-pp-lien>

                    {{-- Boutons révélés par passeport.js : sans JavaScript, l'adresse ci-dessus se copie à la main. --}}
                    <div class="pp-actions" data-pp-actions hidden>
                        <button type="button" class="btn btn-secondary small" data-pp-copier>
                            <i data-lucide="copy" aria-hidden="true"></i> Copier le lien
                        </button>
                        <button type="button" class="btn btn-secondary small" data-pp-partager hidden>
                            <i data-lucide="share-2" aria-hidden="true"></i> Partager
                        </button>
                        <button type="button" class="btn btn-secondary small" data-pp-imprimer>
                            <i data-lucide="printer" aria-hidden="true"></i> Imprimer / PDF
                        </button>
                    </div>
                    <p class="pp-actions__statut" role="status" data-pp-statut></p>
                    <a href="{{ route('front.passeport.qr', ['code' => $passeport->code]) }}" download="passeport-{{ $passeport->numeroCourt() }}.svg" class="pp-partage__telecharger">
                        <i data-lucide="download" aria-hidden="true"></i> Télécharger le QR code
                    </a>
                </section>

                <section class="pp-garanties tr-fil" aria-labelledby="pp-garanties-titre">
                    <h2 id="pp-garanties-titre">Ce que garantit ce passeport</h2>
                    <ul>
                        <li><i data-lucide="badge-check" aria-hidden="true"></i> Chaque réparation a été faite et déclarée terminée par un atelier partenaire de TexTileCycle.</li>
                        <li><i data-lucide="lock" aria-hidden="true"></i> Les informations sont figées à la date de la réparation : l'atelier ne peut plus les modifier.</li>
                        <li><i data-lucide="key-round" aria-hidden="true"></i> Le code du QR est aléatoire et impossible à deviner : un faux passeport n'apparaît pas sur TexTileCycle.</li>
                        <li><i data-lucide="eye-off" aria-hidden="true"></i> Aucune donnée personnelle du propriétaire n'est affichée.</li>
                    </ul>
                    <p class="pp-verifier"><a href="{{ route('front.passeport.verifier') }}">Vérifier un autre passeport</a></p>
                </section>
            </aside>
        </div>

        {{-- Visible seulement à l'impression : la page devient un certificat. --}}
        <p class="pp-impression">Certificat imprimé le {{ now()->locale('fr')->isoFormat('D MMMM YYYY') }} · vérifiable à l'adresse {{ $url }}</p>
    @endif
</main>
@endsection
