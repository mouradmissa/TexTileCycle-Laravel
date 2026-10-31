@extends('layouts.app')

@section('body')
@php
    $user = $backUser ?? auth()->user();
    $canParametres = $user && \App\Modules\Auth\Services\RoleNavigationService::canAccessRoute($user, 'back.parametres');
@endphp
<div class="back-shell">
    <aside class="sidebar">
        <x-logo />
        @if($user)
            <div class="role-switch">
                <div class="role-avatar" aria-hidden="true">{{ $user->initials() }}</div>
                <div>
                    <b>{{ $user->name }}</b>
                    <small>{{ $user->roleLabel() }}</small>
                </div>
            </div>
        @endif
        <nav aria-label="Espace professionnel">
            @foreach($menuItems ?? [] as $item)
                @php
                    $lienActif = request()->routeIs($item['route']) || (substr_count($item['route'], '.') > 1 && request()->routeIs(str($item['route'])->beforeLast('.').'.*'));
                @endphp
                <a href="{{ route($item['route']) }}" @class(['active' => $lienActif]) @if($lienActif) aria-current="page" @endif>
                    <i data-lucide="{{ $item['icon'] }}" aria-hidden="true"></i>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
        <div class="sidebar-bottom">
            @if($canParametres)
                <a href="{{ route('back.parametres') }}" @class(['active' => request()->routeIs('back.parametres')]) @if(request()->routeIs('back.parametres')) aria-current="page" @endif><i data-lucide="settings" aria-hidden="true"></i> Paramètres</a>
            @endif
            <a href="{{ route('front.home') }}"><i data-lucide="external-link" aria-hidden="true"></i> Site citoyen</a>
            <form method="post" action="{{ route('front.logout') }}">
                @csrf
                <button type="submit" class="sidebar-logout"><i data-lucide="log-out" aria-hidden="true"></i> Se déconnecter</button>
            </form>
        </div>
    </aside>

    <main class="back-main" id="contenu" tabindex="-1">
        <header class="back-header">
            <div>
                <span class="breadcrumb">{{ $user?->name ?? 'TexTileCycle' }} <i data-lucide="chevron-right" aria-hidden="true"></i> @yield('page-title', 'Tableau de bord')</span>
                <h1>@yield('page-title', 'Tableau de bord')</h1>
            </div>
            <div class="back-header-actions">
                <form method="get" action="{{ url()->current() }}" class="search-box compact" role="search">
                    <i data-lucide="search" aria-hidden="true"></i>
                    <label class="sr-only" for="recherche-back">Rechercher sur cette page</label>
                    <input id="recherche-back" type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher…">
                    <button type="submit" class="sr-only">Rechercher</button>
                </form>
                @if($user)
                    <div class="user-avatar">
                        <span aria-hidden="true">{{ $user->initials() }}</span>
                        <span class="sr-only">{{ $user->name }}, {{ $user->roleLabel() }}</span>
                    </div>
                @endif
            </div>
        </header>

        @yield('content')
    </main>

    <a href="{{ route('front.home') }}" class="back-to-front">Voir le site citoyen</a>
</div>
@endsection
