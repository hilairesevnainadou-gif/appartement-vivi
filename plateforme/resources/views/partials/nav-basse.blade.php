@php($page = $page ?? '')
{{-- Navigation basse, visible sur mobile uniquement (masquée en CSS à partir de 992 px) --}}
<nav class="vivi-bottomnav" aria-label="Navigation mobile">
    <a href="{{ route('accueil') }}" @if($page === 'accueil') aria-current="page" @endif><i class="bi bi-house-door" aria-hidden="true"></i>Accueil</a>
    <a href="{{ route('recherche', ['categorie' => 'logement']) }}" @if($page === 'recherche') aria-current="page" @endif><i class="bi bi-house-heart" aria-hidden="true"></i>Logements</a>
    <a href="{{ route('recherche', ['categorie' => 'salle']) }}" @if($page === 'salles') aria-current="page" @endif><i class="bi bi-balloon" aria-hidden="true"></i>Salles</a>
    {{-- Seulement une fois connecté : pas de réservations sur le site public --}}
    @auth
        <a href="{{ route('espace.client') }}" @if($page === 'reservations') aria-current="page" @endif><i class="bi bi-ticket-perforated" aria-hidden="true"></i>Réservations</a>
    @endauth
    <a href="{{ auth()->check() ? route('espace') : route('connexion') }}" @if(in_array($page, ['connexion', 'espace'], true)) aria-current="page" @endif><i class="bi bi-person" aria-hidden="true"></i>Compte</a>
</nav>
