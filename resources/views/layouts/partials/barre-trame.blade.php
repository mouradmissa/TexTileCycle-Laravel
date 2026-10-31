{{-- Barre de navigation « trame » (styles : css/trame.css, comportement : js/trame.js).
     Fixe en haut de l'écran et toujours sur une seule ligne : sur petit écran, les liens défilent au doigt. --}}
<header class="tr-nav" data-tr-nav>
    <div class="tr-logo"><x-logo :light="true" /></div>

    <nav class="tr-liens" aria-label="Navigation principale">
        @include('layouts.partials.front-liens')
    </nav>

    <div class="tr-compte">
        <span class="tr-langue">
            <i data-lucide="globe" aria-hidden="true"></i>
            <span lang="fr">FR</span>
            <span class="tr-sr">Langue du site : français</span>
        </span>
        @auth
            @if(auth()->user()->canAccessBackOffice())
                <a href="{{ route('back.dashboard') }}" class="tr-lien-compte">Espace professionnel</a>
            @endif
            @if(auth()->user()->isCitoyen())
                <a href="{{ route('front.profil') }}" class="tr-utilisateur" aria-label="Profil de {{ auth()->user()->name }}" @if(request()->routeIs('front.profil')) aria-current="page" @endif>{{ auth()->user()->name }}</a>
            @else
                <span class="tr-utilisateur">{{ auth()->user()->name }}</span>
            @endif
            <form method="post" action="{{ route('front.logout') }}" class="tr-form">
                @csrf
                <button type="submit" class="tr-pilule">Déconnexion</button>
            </form>
        @else
            <a href="{{ route('front.login') }}" class="tr-pilule">Connexion</a>
            <a href="{{ route('front.register') }}" class="tr-pilule est-claire">Inscription</a>
        @endauth
    </div>
</header>
