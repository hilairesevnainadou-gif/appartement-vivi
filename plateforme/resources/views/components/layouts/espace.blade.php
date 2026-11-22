{{-- Pages du compte (profil, notifications, parrainage) : dans l'espace propriétaire pour un propriétaire ou un
     gestionnaire (menu latéral, il reste dans son espace), sinon dans le site client. --}}
@props(['titre' => null, 'page' => 'compte'])
@if (auth()->user()?->estProprietaireOuGestionnaire())
    <x-layouts.pro :titre="$titre" :page="$page">{{ $slot }}</x-layouts.pro>
@else
    <x-layouts.public :titre="$titre" page="compte" classe-corps="bg-sand">{{ $slot }}</x-layouts.public>
@endif
