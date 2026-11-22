{{-- Gabarit de l'espace propriétaire et gestionnaire (repris de la maquette pro-*.html).
     Usage : <x-layouts.pro titre="Tableau de bord" page="tableau"> … </x-layouts.pro> --}}
@props(['titre' => 'Espace propriétaire', 'page' => ''])
@php
    $utilisateur = auth()->user();
    $libelleRole = $utilisateur->hasRole(\App\Enums\Role::Gestionnaire->value) ? 'Gestionnaire' : 'Propriétaire';
    $initiales = $utilisateur->initiales();

    // Écrans sans route : un message « bientôt » au clic, avec la pastille « bientôt » de la maquette.
    // Le gestionnaire accueille les clients sur les logements confiés : il ne gère ni logements, ni argent, ni abonnement.
    $estProprietaire = $utilisateur->hasRole(\App\Enums\Role::Proprietaire->value);
    $reserveProprietaire = ['logements', 'revenus', 'gestionnaires', 'agents', 'abonnement'];   // le gestionnaire garde Réservations
    $menu = [
        ['id' => 'tableau', 'route' => 'espace.proprietaire', 'label' => 'Tableau de bord', 'icone' => 'bi-speedometer2'],
        ['id' => 'logements', 'route' => 'pro.logements', 'label' => 'Logements et salles', 'icone' => 'bi-houses'],
        ['id' => 'calendrier', 'route' => 'pro.calendrier', 'label' => 'Calendrier', 'icone' => 'bi-calendar3'],
        ['id' => 'scanner', 'route' => 'pro.scanner', 'label' => 'Scanner un QR Code', 'icone' => 'bi-qr-code-scan'],
        ['id' => 'reservations', 'route' => 'pro.reservations', 'label' => 'Réservations', 'icone' => 'bi-ticket-perforated'],
        ['id' => 'revenus', 'route' => 'pro.revenus', 'label' => 'Revenus', 'icone' => 'bi-cash-stack'],
        ['id' => 'gestionnaires', 'route' => 'pro.gestionnaires', 'label' => 'Gestionnaires', 'icone' => 'bi-person-gear'],
        ['id' => 'agents', 'route' => 'pro.agents', 'label' => 'Agents marketing', 'icone' => 'bi-megaphone'],
        ['id' => 'abonnement', 'route' => 'pro.abonnement', 'label' => 'Abonnement', 'icone' => 'bi-credit-card-2-front'],
    ];
    if (! $estProprietaire) {
        $menu = array_values(array_filter($menu, fn ($m) => ! in_array($m['id'], $reserveProprietaire, true)));
    }
    // Un propriétaire ou un gestionnaire peut aussi être agent marketing pour d'autres logements
    if ($utilisateur->estAgent()) {
        $menu[] = ['id' => 'agent', 'route' => 'agent', 'label' => 'Espace agent', 'icone' => 'bi-link-45deg'];
    }
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>{{ $titre }} · Espace {{ mb_strtolower($libelleRole) }} · {{ config('app.name') }}</title>
    <meta name="theme-color" content="#0a3d3e">
    <link rel="icon" href="{{ asset('images/logo/favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('images/logo/favicon-32.png') }}" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('images/logo/apple-touch-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="pro" data-page="{{ $page }}">
    <a class="skip-link visually-hidden-focusable" href="#contenu">Aller au contenu</a>

    <div class="pro-shell">
        <aside class="pro-side">
            <a class="pro-side__brand" href="{{ route('espace.proprietaire') }}" aria-label="VIVI, tableau de bord">
                <img src="{{ asset('images/logo/vivi-symbole-blanc.svg') }}" alt="" width="40" height="40">
                <span class="vivi-brand__text"><b>VIVI</b><small>Appartements<br>&amp; salles.</small></span>
            </a>
            <div class="pro-side__label">Espace {{ mb_strtolower($libelleRole) }}</div>
            <nav aria-label="Menu {{ mb_strtolower($libelleRole) }}">
                <ul class="pro-nav">
                    @foreach ($menu as $m)
                        @include('partials.pro-lien', ['m' => $m, 'page' => $page])
                    @endforeach
                </ul>
            </nav>
            <div class="pro-side__foot">
                <div class="pro-user">
                    <span class="avatar" aria-hidden="true">{{ $initiales }}</span>
                    <span><strong>{{ $utilisateur->name }}</strong><small>{{ $libelleRole }}</small></span>
                </div>
                @php $nonLues = $utilisateur->notificationsSite()->whereNull('read_at')->count(); @endphp
                <a class="retour" href="{{ route('profil') }}" @if ($page === 'profil') aria-current="page" @endif><i class="bi bi-person" aria-hidden="true"></i>Mon profil</a>
                <a class="retour" href="{{ route('notifications') }}" @if ($page === 'notifications') aria-current="page" @endif><i class="bi bi-bell" aria-hidden="true"></i>Notifications
                    @if ($nonLues)<span class="pro-side__badge" aria-label="{{ $nonLues }} non lues">{{ $nonLues > 9 ? '9+' : $nonLues }}</span>@endif</a>
                <a class="retour" href="{{ route('accueil') }}"><i class="bi bi-box-arrow-left" aria-hidden="true"></i>Retour au site client</a>
                <form method="POST" action="{{ route('deconnexion') }}">
                    @csrf
                    <button class="retour" type="submit"><i class="bi bi-power" aria-hidden="true"></i>Déconnexion</button>
                </form>
            </div>
        </aside>

        <div>
            <header class="pro-top">
                <a class="vivi-brand" href="{{ route('espace.proprietaire') }}" aria-label="Tableau de bord">
                    <img src="{{ asset('images/logo/vivi-symbole.svg') }}" alt="" width="36" height="36">
                </a>
                <span class="pro-top__titre">{{ $titre }}</span>
                @include('partials.cloche')
                @include('partials.profil', ['compact' => true, 'contexte' => 'pro'])
            </header>

            <main id="contenu" class="pro-main">
                <div class="pro-wrap">
                    @if (session('statut'))
                        <div class="note-box mb-3" role="status"><i class="bi bi-check-circle" aria-hidden="true"></i><span>{{ session('statut') }}</span></div>
                    @endif
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>

    {{-- Navigation basse (mobile) --}}
    <nav class="pro-bottomnav" aria-label="Navigation {{ mb_strtolower($libelleRole) }}">
        <a href="{{ route('espace.proprietaire') }}" @if($page === 'tableau') aria-current="page" @endif><i class="bi bi-speedometer2" aria-hidden="true"></i>Accueil</a>
        @if ($estProprietaire)
            <a href="{{ route('pro.logements') }}" @if($page === 'logements') aria-current="page" @endif><i class="bi bi-houses" aria-hidden="true"></i>Logements</a>
        @endif
        <a class="scan" href="{{ route('pro.scanner') }}" @if($page === 'scanner') aria-current="page" @endif><span class="rond"><i class="bi bi-qr-code-scan" aria-hidden="true"></i></span>Scanner</a>
        <a href="{{ route('pro.calendrier') }}" @if($page === 'calendrier') aria-current="page" @endif><i class="bi bi-calendar3" aria-hidden="true"></i>Calendrier</a>
        <button type="button" data-bs-toggle="offcanvas" data-bs-target="#pro-plus" aria-controls="pro-plus"><i class="bi bi-list" aria-hidden="true"></i>Plus</button>
    </nav>
    <div class="offcanvas offcanvas-bottom pro-plus" id="pro-plus" tabindex="-1" aria-labelledby="pro-plus-titre" style="height:auto;border-radius:20px 20px 0 0">
        <div class="offcanvas-header">
            <h2 class="offcanvas-title h5 mb-0" id="pro-plus-titre">{{ $utilisateur->name }}</h2>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fermer"></button>
        </div>
        <div class="offcanvas-body pt-0">
            <ul class="pro-nav">
                @foreach (array_filter($menu, fn ($m) => ! in_array($m['id'], ['tableau', 'logements', 'calendrier', 'scanner'], true)) as $m)
                    @include('partials.pro-lien', ['m' => $m, 'page' => $page])
                @endforeach
                <li><a href="{{ route('accueil') }}"><i class="bi bi-box-arrow-left" aria-hidden="true"></i>Retour au site client</a></li>
                <li>
                    <form method="POST" action="{{ route('deconnexion') }}">
                        @csrf
                        <button type="submit"><i class="bi bi-power" aria-hidden="true"></i>Déconnexion</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
