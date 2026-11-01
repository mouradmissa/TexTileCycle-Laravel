{{-- Reçu de don à imprimer ou enregistrer en PDF. Page autonome : seule la fiche s'imprime. --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Reçu {{ $numero }} · TexTileCycle</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap">
    <link rel="stylesheet" href="{{ asset('css/don-recu.css') }}">
</head>
<body>
    <main class="dr-page">
        <div class="dr-barre">
            <a href="{{ route('front.dons') }}" class="dr-lien">← Retour à mes dons</a>
            <button type="button" class="dr-bouton" data-imprimer>Imprimer / Enregistrer en PDF</button>
            <p class="dr-aide">Dans la fenêtre d'impression, choisissez « Enregistrer au format PDF ».</p>
            <a href="{{ $urlVerification }}" target="_blank" rel="noopener" class="dr-lien">Vérifier ce reçu</a>
        </div>

        <article class="dr-recu" aria-label="Reçu de don {{ $numero }}">
            <header class="dr-tete">
                <div>
                    <p class="dr-kicker">TexTileCycle</p>
                    <h1>Reçu de don</h1>
                    <p class="dr-numero">N° {{ $numero }}</p>
                </div>
                <div class="dr-qr" aria-label="QR code de vérification">{!! $qr !!}</div>
            </header>

            <section class="dr-bloc">
                <h2>Donateur</h2>
                <dl>
                    <div><dt>Nom</dt><dd>{{ $don->donateur?->name ?? '—' }}</dd></div>
                    <div><dt>Date du don</dt><dd>{{ $don->created_at->format('d/m/Y') }}</dd></div>
                    @if($don->date_reponse)
                        <div><dt>Accepté le</dt><dd>{{ $don->date_reponse->format('d/m/Y') }}</dd></div>
                    @endif
                </dl>
            </section>

            <section class="dr-bloc">
                <h2>Vêtement donné</h2>
                <dl>
                    <div><dt>Type</dt><dd>{{ $don->vetement?->type ?: '—' }}</dd></div>
                    @if($don->vetement?->size)
                        <div><dt>Taille</dt><dd>{{ $don->vetement->size }}</dd></div>
                    @endif
                    @if($don->vetement?->condition_label)
                        <div><dt>État</dt><dd>{{ $don->vetement->condition_label }}</dd></div>
                    @endif
                </dl>
            </section>

            <section class="dr-bloc">
                <h2>Association bénéficiaire</h2>
                <dl>
                    <div><dt>Nom</dt><dd>{{ $don->association?->nom ?? '—' }}</dd></div>
                    @if($don->association?->adresse)
                        <div><dt>Adresse</dt><dd>{{ $don->association->adresse }}</dd></div>
                    @endif
                    @if($don->association?->telephone)
                        <div><dt>Téléphone</dt><dd>{{ $don->association->telephone }}</dd></div>
                    @endif
                </dl>
            </section>

            <footer class="dr-pied">
                <p>Merci pour votre geste : ce don prolonge la vie d'un vêtement.</p>
                <p class="dr-petit">Scannez le QR code pour vérifier l'authenticité de ce reçu.</p>
            </footer>
        </article>
    </main>

    <script src="{{ asset('js/don-recu.js') }}" defer></script>
</body>
</html>
