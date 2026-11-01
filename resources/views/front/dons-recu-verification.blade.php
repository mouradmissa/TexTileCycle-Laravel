{{-- Page publique ouverte par le QR code d'un reçu : authenticité seulement, aucune donnée personnelle. --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Vérification d'un reçu · TexTileCycle</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap">
    <link rel="stylesheet" href="{{ asset('css/don-recu.css') }}">
</head>
<body>
    <main class="dr-page">
        <article class="dr-recu dr-verif {{ $valide ? 'is-valide' : 'is-invalide' }}" role="status">
            <p class="dr-kicker">TexTileCycle</p>
            @if($valide)
                <h1>Reçu authentique</h1>
                <p class="dr-numero">N° {{ $numero }}</p>
                <dl>
                    <div><dt>Association</dt><dd>{{ $don->association?->nom ?? '—' }}</dd></div>
                    <div><dt>Vêtement</dt><dd>{{ $don->vetement?->type ?: '—' }}</dd></div>
                    <div><dt>Date du don</dt><dd>{{ $don->created_at->format('d/m/Y') }}</dd></div>
                </dl>
                <p class="dr-petit">Ce don a bien été accepté par l'association.</p>
            @else
                <h1>Reçu non valide</h1>
                <p>Ce reçu n'existe pas ou le don n'a pas été accepté.</p>
            @endif
        </article>
    </main>
</body>
</html>
