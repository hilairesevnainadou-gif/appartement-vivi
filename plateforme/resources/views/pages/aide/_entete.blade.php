{{-- En-tête commun des pages d'aide : passage d'une page à l'autre --}}
<nav class="segmented aide-onglets mb-4" aria-label="Pages d’aide">
    <a @class(['segmented__lien', 'is-actif' => request()->routeIs('aide.annulation')]) href="{{ route('aide.annulation') }}" @if(request()->routeIs('aide.annulation')) aria-current="page" @endif>Annulation et report</a>
    <a @class(['segmented__lien', 'is-actif' => request()->routeIs('aide.conditions')]) href="{{ route('aide.conditions') }}" @if(request()->routeIs('aide.conditions')) aria-current="page" @endif>Conditions générales</a>
    @if (\App\Support\Parametres::get('confidentialite_publiee') || request()->routeIs('aide.confidentialite'))
        <a @class(['segmented__lien', 'is-actif' => request()->routeIs('aide.confidentialite')]) href="{{ route('aide.confidentialite') }}" @if(request()->routeIs('aide.confidentialite')) aria-current="page" @endif>Confidentialité</a>
    @endif
</nav>
