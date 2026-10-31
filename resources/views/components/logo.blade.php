@props(['compact' => false, 'light' => false])

<a href="{{ route('front.home') }}" class="logo" aria-label="TexTileCycle, accueil" @if($light) style="color:white" @endif>
    <span class="logo-mark" aria-hidden="true"><i data-lucide="leaf"></i></span>
    @unless($compact)
        <span>TexTile<span>Cycle</span></span>
    @endunless
</a>
