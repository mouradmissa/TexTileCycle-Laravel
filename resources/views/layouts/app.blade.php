<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="@yield('viewport', 'width=device-width, initial-scale=1')">
    <title>@yield('title', 'TexTileCycle') — Donnez une seconde vie à vos vêtements</title>
    <meta name="description" content="La plateforme tunisienne de l'économie circulaire textile. Réparez, donnez et mesurez votre impact.">
    {{-- Actualiser une page ramène en haut (une ancre #… reste respectée par le navigateur).
         Chrome décide de restaurer la position avec le réglage de la page quittée : « manual » est donc posé
         sur toutes les pages, dès le <head>, et la position est rendue à la main au retour arrière. --}}
    <script>
        (function () {
            if (!('scrollRestoration' in history)) return;
            history.scrollRestoration = 'manual';

            var cle = 'tc-defilement:' + location.pathname + location.search;
            var navigation = performance.getEntriesByType ? performance.getEntriesByType('navigation')[0] : null;
            try {
                var y = Number(sessionStorage.getItem(cle));
                if (navigation && navigation.type === 'back_forward' && y > 0) {
                    // instant : textilecycle.css met scroll-behavior: smooth sur toute la page.
                    addEventListener('load', function () { scrollTo({ top: y, behavior: 'instant' }); });
                }
                addEventListener('pagehide', function () {
                    try { sessionStorage.setItem(cle, String(Math.round(scrollY))); } catch (e) {}
                });
            } catch (e) {
                // Stockage indisponible (navigation privée stricte) : le retour arrière revient en haut.
            }
        })();
    </script>
    <link rel="icon" href="{{ asset('icon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/textilecycle.css') }}">
    @stack('styles')
</head>
<body>
    <a class="skip-link" href="#contenu">Aller au contenu</a>
    @yield('body')
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="{{ asset('js/app.js') }}"></script>
    @stack('scripts')
</body>
</html>
