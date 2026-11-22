@php($page = $page ?? '')
@if (($entete ?? 'normal') === 'tunnel')
    {{-- Tunnel de paiement : logo et mention « Paiement sécurisé », sans menu --}}
    <header class="vivi-header tunnel-header">
        <div class="container">
            <a class="vivi-brand" href="{{ route('accueil') }}" aria-label="VIVI, appartements et salles : retour à l’accueil">
                <img src="{{ asset('images/logo/vivi-symbole.svg') }}" alt="" width="40" height="40">
                <span class="vivi-brand__text"><b>VIVI</b><small>Appartements<br>&amp; salles.</small></span>
            </a>
            <span class="secure"><i class="bi bi-shield-lock-fill" aria-hidden="true"></i><span class="d-sm-none">Sécurisé</span><span class="d-none d-sm-inline">Paiement sécurisé</span></span>
        </div>
    </header>
@else
<header class="vivi-header">
    <div class="container">
        <a class="vivi-brand" href="{{ route('accueil') }}" aria-label="VIVI, appartements et salles : retour à l’accueil">
            <img src="{{ asset('images/logo/vivi-symbole.svg') }}" alt="" width="40" height="40">
            <span class="vivi-brand__text"><b>VIVI</b><small>Appartements<br>&amp; salles.</small></span>
        </a>

        <nav aria-label="Navigation principale">
            <ul class="vivi-nav">
                <li><a href="{{ route('recherche', ['categorie' => 'logement']) }}" @if($page === 'recherche') aria-current="page" @endif>Logements</a></li>
                <li><a href="{{ route('recherche', ['categorie' => 'salle']) }}" @if($page === 'salles') aria-current="page" @endif>Salles et événements</a></li>
                <li class="vivi-nav__large"><a href="{{ route('accueil') }}#services">Services</a></li>
                <li class="vivi-nav__large"><a href="{{ route('accueil') }}#comment-ca-marche">Comment ça marche</a></li>
                <li><a href="{{ route('proprietaires') }}" @if($page === 'proprietaires') aria-current="page" @endif>Propriétaires</a></li>
            </ul>
        </nav>

        <div class="vivi-header__actions">
            @auth
                @if (auth()->user()->estProprietaireOuGestionnaire())
                    <a class="btn btn-ghost btn-sm d-none d-lg-inline-flex" href="{{ route('espace.proprietaire') }}"><i class="bi bi-speedometer2" aria-hidden="true"></i>Tableau de bord</a>
                @elseif (auth()->user()->estAgent())
                    <a class="btn btn-ghost btn-sm d-none d-lg-inline-flex" href="{{ route('agent') }}" @if($page === 'agent') aria-current="page" @endif><i class="bi bi-megaphone" aria-hidden="true"></i>Espace agent</a>
                @endif
                @include('partials.cloche')
                @include('partials.profil')
            @else
                <a class="btn btn-outline-primary btn-sm" href="{{ route('connexion') }}"><i class="bi bi-person" aria-hidden="true"></i>Connexion</a>
            @endauth
        </div>
    </div>
</header>
@endif

@if (session('statut') && $page !== 'connexion')
    <div class="container mt-3">
        <div class="note-box" role="status"><i class="bi bi-check-circle" aria-hidden="true"></i><span>{{ session('statut') }}</span></div>
    </div>
@endif
