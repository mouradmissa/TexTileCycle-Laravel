<a href="{{ route('front.home') }}" @class(['active' => request()->routeIs('front.home')]) @if(request()->routeIs('front.home')) aria-current="page" @endif>Accueil</a>
<a href="{{ route('front.ateliers') }}" @class(['active' => request()->routeIs('front.ateliers')]) @if(request()->routeIs('front.ateliers')) aria-current="page" @endif>Ateliers</a>
<a href="{{ route('front.associations') }}" @class(['active' => request()->routeIs('front.associations')]) @if(request()->routeIs('front.associations')) aria-current="page" @endif>Associations</a>
@auth
    <a href="{{ route('front.vetements') }}" @class(['active' => request()->routeIs('front.vetements')]) @if(request()->routeIs('front.vetements')) aria-current="page" @endif>Mes vêtements</a>
    <a href="{{ route('front.historique') }}" @class(['active' => request()->routeIs('front.historique')]) @if(request()->routeIs('front.historique')) aria-current="page" @endif>Historique</a>
    <a href="{{ route('front.rdv') }}" @class(['active' => request()->routeIs('front.rdv*')]) @if(request()->routeIs('front.rdv*')) aria-current="page" @endif>Rendez-vous</a>
    <a href="{{ route('front.passeports') }}" @class(['active' => request()->routeIs('front.passeports')]) @if(request()->routeIs('front.passeports')) aria-current="page" @endif>Passeports</a>
    <a href="{{ route('front.dons') }}" @class(['active' => request()->routeIs('front.dons')]) @if(request()->routeIs('front.dons')) aria-current="page" @endif>Dons</a>
    <a href="{{ route('front.signalements.index') }}" @class(['active' => request()->routeIs('front.signalements.*')]) @if(request()->routeIs('front.signalements.*')) aria-current="page" @endif>Mes signalements</a>
    @if(auth()->user()->isCitoyen())
        <a href="{{ route('front.profil') }}" @class(['active' => request()->routeIs('front.profil')]) @if(request()->routeIs('front.profil')) aria-current="page" @endif>Profil</a>
    @endif
@endauth
