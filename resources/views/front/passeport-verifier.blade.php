@extends('layouts.trame')

@section('title', $introuvable ? 'Passeport introuvable' : 'Vérifier un passeport de réparation')

@push('styles')
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="{{ asset('css/passeport.css') }}">
@endpush

{{-- Deux usages : la page « Vérifier un passeport », et la réponse 404 quand le code d'un QR ne correspond à rien. --}}
@section('bandeau')
    <nav class="tr-ariane" aria-label="Fil d'Ariane">
        <ol>
            <li><a href="{{ route('front.home') }}">Accueil</a></li>
            <li><span aria-current="page">{{ $introuvable ? 'Passeport introuvable' : 'Vérifier un passeport' }}</span></li>
        </ol>
    </nav>
    <div class="tr-bandeau__grille">
        <div class="tr-bandeau__principal">
            <p class="pp-kicker">Passeport de réparation</p>
            <h1>{{ $introuvable ? 'Ce passeport est introuvable' : 'Vérifier un passeport' }}</h1>
            <p class="tr-bandeau__intro">
                @if ($introuvable)
                    Le lien ouvert ne correspond à aucun passeport. Le QR est peut-être abîmé ou l'adresse mal copiée.
                @else
                    Vous achetez ou recevez une pièce réparée ? Vérifiez son passeport : il prouve qu'un atelier partenaire l'a réparée.
                @endif
            </p>
        </div>
    </div>
@endsection

@section('content')
<main class="container page-content pp">
    <div class="pp-verif">
        <form method="get" action="{{ route('front.passeport.rechercher') }}" class="pp-verif__form tr-fil" novalidate>
            <h2>Saisir le code</h2>
            <label for="pp-code">Code du passeport ou adresse écrite sous le QR</label>
            <input id="pp-code" name="code" type="text" value="{{ old('code') }}" autocomplete="off" spellcheck="false"
                   placeholder="Ex. : {{ url('/passeport') }}/Ab3dEf6hIj9kLm2nOp5qRs"
                   @error('code') aria-invalid="true" aria-describedby="pp-code-erreur" @enderror>
            @error('code')
                <p id="pp-code-erreur" class="pp-verif__erreur">{{ $message }}</p>
            @enderror
            <button type="submit" class="btn btn-primary">
                <i data-lucide="search-check" aria-hidden="true"></i> Vérifier
            </button>
        </form>

        <section class="pp-garanties tr-fil" aria-labelledby="pp-aide-titre">
            <h2 id="pp-aide-titre">Où trouver le code ?</h2>
            <ul>
                <li><i data-lucide="qr-code" aria-hidden="true"></i> Scannez le QR de l'étiquette cousue sur la pièce : la page s'ouvre directement.</li>
                <li><i data-lucide="link" aria-hidden="true"></i> Sous le QR est écrite l'adresse complète : collez-la ci-contre telle quelle.</li>
                <li><i data-lucide="case-sensitive" aria-hidden="true"></i> Le code compte 22 lettres et chiffres ; majuscules et minuscules comptent. Le numéro court (ex. AB3D-EF6H) ne suffit pas.</li>
            </ul>
        </section>
    </div>
</main>
@endsection
