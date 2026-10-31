{{-- Pied de page du site public. $trame = true : version « trame » (fond sombre, aligné sur la trame). --}}
<footer @class(['footer', 'tr-pied' => $trame ?? false])>
    <div class="container footer-grid">
        <div>
            <x-logo :light="true" />
            <p>La plateforme tunisienne de l'économie circulaire textile.</p>
        </div>
        <nav aria-label="Explorer">
            <h2>Explorer</h2>
            <ul>
                <li><a href="{{ route('front.ateliers') }}">Ateliers</a></li>
                <li><a href="{{ route('front.associations') }}">Associations</a></li>
                <li><a href="{{ route('front.home') }}#chiffres">Notre impact</a></li>
            </ul>
        </nav>
        <nav aria-label="Ressources">
            <h2>Ressources</h2>
            <ul>
                <li><a href="{{ route('front.home') }}#parcours">Comment ça marche</a></li>
                <li><a href="mailto:hello@textilecycle.tn">Contact</a></li>
            </ul>
        </nav>
        <div>
            <h2>Suivez-nous</h2>
            <p><a href="mailto:hello@textilecycle.tn">hello@textilecycle.tn</a></p>
            <p>Tunis, Tunisie</p>
        </div>
    </div>
    <div class="container footer-bottom"><p>© 2026 TexTileCycle <span>Fait avec soin pour la planète.</span></p></div>
</footer>
