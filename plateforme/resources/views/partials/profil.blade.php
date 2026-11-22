{{-- Menu profil de la personne connectée (en-tête du site et barre haute de l'espace propriétaire).
     $compact : pastille seule, sans nom (barre haute mobile de l'espace propriétaire). --}}
@php
    $u = auth()->user();
    $compact = $compact ?? false;
    $contact = $u->email ?? ($u->telephone ? \App\Support\Telephone::lisible($u->telephone) : null);
@endphp
<div class="dropdown profil">
    <button class="profil__bouton" type="button" data-bs-toggle="dropdown" data-bs-offset="0,10" aria-expanded="false" aria-label="Mon compte : {{ $u->name }}">
        <span class="avatar avatar--sm" aria-hidden="true">{{ $u->initiales() }}</span>
        @unless ($compact)
            <span class="profil__nom d-none d-md-flex"><strong>{{ $u->prenom() }}</strong><small>{{ $u->libelleRole() }}</small></span>
            <i class="bi bi-chevron-down profil__fleche d-none d-md-inline" aria-hidden="true"></i>
        @endunless
    </button>
    <div class="dropdown-menu dropdown-menu-end profil__menu">
        <div class="profil__tete">
            <span class="avatar" aria-hidden="true">{{ $u->initiales() }}</span>
            <span class="profil__identite">
                <strong>{{ $u->name }}</strong>
                @if ($contact)<small>{{ $contact }}</small>@endif
            </span>
        </div>
        <ul class="profil__liens">
            @if ($u->estProprietaireOuGestionnaire())
                <li><a class="dropdown-item" href="{{ route('espace.proprietaire') }}"><i class="bi bi-speedometer2" aria-hidden="true"></i>Tableau de bord</a></li>
            @endif
            @if ($u->estAgent())
                <li><a class="dropdown-item" href="{{ route('agent') }}"><i class="bi bi-megaphone" aria-hidden="true"></i>Espace agent</a></li>
            @endif
            @if ($u->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')))
                <li><a class="dropdown-item" href="/admin"><i class="bi bi-shield-lock" aria-hidden="true"></i>Administration</a></li>
            @endif
            <li><a class="dropdown-item" href="{{ route('espace.client') }}"><i class="bi bi-ticket-perforated" aria-hidden="true"></i>Mes réservations</a></li>
            <li><a class="dropdown-item" href="{{ route('notifications') }}"><i class="bi bi-bell" aria-hidden="true"></i>Notifications</a></li>
            <li><a class="dropdown-item" href="{{ route('parrainage') }}"><i class="bi bi-gift" aria-hidden="true"></i>Parrainage et bonus</a></li>
            <li><a class="dropdown-item" href="{{ route('profil') }}"><i class="bi bi-person" aria-hidden="true"></i>Mon profil</a></li>
            @if (($contexte ?? 'site') === 'pro')
                <li><a class="dropdown-item" href="{{ route('accueil') }}"><i class="bi bi-box-arrow-left" aria-hidden="true"></i>Retour au site client</a></li>
            @endif
        </ul>
        <form method="POST" action="{{ route('deconnexion') }}" class="profil__sortie">
            @csrf
            <button class="dropdown-item" type="submit"><i class="bi bi-power" aria-hidden="true"></i>Déconnexion</button>
        </form>
    </div>
</div>
