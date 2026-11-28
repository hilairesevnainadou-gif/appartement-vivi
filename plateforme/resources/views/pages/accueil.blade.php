<x-layouts.public
    page="accueil"
    titre="Appartements meublés et salles à l’heure, à la demi-journée ou à la journée"
    description="Réservez un appartement meublé, une salle de fête ou de conférence vérifiés au Bénin. Services à la carte, paiement Mobile Money, entrée par QR Code.">

    {{-- ===== Bandeau et recherche ===== --}}
    <section class="hero" aria-labelledby="titre-accueil">
        <picture>
            <source media="(max-width: 767.98px)" srcset="{{ asset('images/hero-900.webp') }}">
            <img class="hero__img" src="{{ asset('images/hero-1600.webp') }}" alt="" width="1600" height="1000" fetchpriority="high">
        </picture>
        <div class="container">
            <span class="chip chip--hero"><i class="bi bi-geo-alt-fill" aria-hidden="true"></i>{{ $villesBandeau->implode(' · ') }}@if ($autresVilles) et {{ $autresVilles }} autre{{ $autresVilles > 1 ? 's' : '' }} ville{{ $autresVilles > 1 ? 's' : '' }}@endif</span>
            <h1 id="titre-accueil">Un appartement ou une salle, pour une heure, une demi-journée ou la journée.</h1>
            <p class="hero__lead">Séjour, fête, mariage, réunion : choisissez un lieu vérifié, ajoutez vos services, payez par Mobile Money et entrez avec votre QR Code.</p>

            <form class="search-card" action="{{ route('recherche') }}" method="get" role="search" aria-label="Rechercher un logement ou une salle">
                {{-- Logement (séjour) ou salle (fête, conférence, réunion) --}}
                <div class="segmented segmented--pays mb-3" role="radiogroup" aria-label="Que cherchez-vous ?">
                    <input type="radio" name="categorie" id="r-cat-logement" value="logement" checked>
                    <label for="r-cat-logement"><i class="bi bi-house-door" aria-hidden="true"></i>Un logement</label>
                    <input type="radio" name="categorie" id="r-cat-salle" value="salle">
                    <label for="r-cat-salle"><i class="bi bi-balloon" aria-hidden="true"></i>Une salle de fête ou de conférence</label>
                </div>
                <div class="search-grid">
                    <div class="sg-ville">
                        <label class="form-label" for="r-ville">Où ?</label>
                        <select class="form-select" id="r-ville" name="ville">
                            <option value="">Toutes les villes</option>
                            @foreach ($villes as $ville)
                                <option>{{ $ville }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sg-formule">
                        <span class="form-label d-block" id="lbl-r-formule">Pour combien de temps ?</span>
                        <div class="segmented" role="radiogroup" aria-labelledby="lbl-r-formule">
                            <input type="radio" name="formule" id="r-heure" value="heure" checked><label for="r-heure">Heure</label>
                            <input type="radio" name="formule" id="r-demi" value="demi_journee"><label for="r-demi">Demi-journée</label>
                            <input type="radio" name="formule" id="r-jour" value="journee"><label for="r-jour">Journée</label>
                        </div>
                    </div>
                    <div class="sg-date">
                        <label class="form-label" for="r-date">Quand ?</label>
                        <input class="form-control" type="date" id="r-date" name="date" min="{{ today()->toDateString() }}" value="{{ today()->toDateString() }}">
                    </div>
                    <div class="sg-voyageurs">
                        <label class="form-label" for="r-voyageurs" data-libelle-logement="Voyageurs" data-libelle-salle="Capacité">Voyageurs</label>
                        <select class="form-select" id="r-voyageurs" name="voyageurs">
                            @for ($n = 1; $n <= 6; $n++)
                                <option @selected($n === 2) data-pour="logement">{{ $n }}</option>
                            @endfor
                            @foreach ([1 => 'Toutes', 20 => '20 places et plus', 50 => '50 places et plus', 100 => '100 places et plus', 200 => '200 places et plus', 300 => '300 places et plus', 500 => '500 places et plus'] as $n => $libelle)
                                <option value="{{ $n }}" data-pour="salle" hidden disabled>{{ $libelle }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sg-submit">
                        <button class="btn btn-cta btn-lg w-100" type="submit"><i class="bi bi-search" aria-hidden="true"></i>Rechercher</button>
                    </div>
                </div>
                <button class="btn btn-link pres-de-moi" type="button" data-pres-de-moi><i class="bi bi-crosshair" aria-hidden="true"></i>Chercher près de moi</button>
            </form>
        </div>
    </section>

    {{-- ===== Engagements ===== --}}
    <section class="trust-strip" aria-label="Nos engagements">
        <div class="container">
            <ul>
                <li><i class="bi bi-patch-check" aria-hidden="true"></i>Logements et salles vérifiés par VIVI</li>
                <li><i class="bi bi-phone" aria-hidden="true"></i>Paiement Mobile Money ou carte</li>
                <li><i class="bi bi-qr-code-scan" aria-hidden="true"></i>Entrée par QR Code</li>
                <li><i class="bi bi-receipt" aria-hidden="true"></i>Prix total affiché avant de payer</li>
            </ul>
        </div>
    </section>

    {{-- ===== Publicité (espace « Accueil, en haut ») ===== --}}
    <x-pub emplacement="accueil_haut" conteneur />

    {{-- ===== Ce que propose VIVI ===== --}}
    <section class="section pb-0" id="offre" aria-labelledby="titre-offre">
        <div class="container">
            <div class="section-head">
                <span class="eyebrow">Ce que propose VIVI</span>
                <h2 id="titre-offre">Se loger, fêter, se réunir, louer son bien</h2>
            </div>
            <div class="row g-3">
                @foreach ([
                    ['bi-house-heart', 'Appartements meublés', 'Chambre, studio, appartement ou résidence, à l’heure, à la demi-journée ou à la journée.', 'Voir les logements', route('recherche', ['categorie' => 'logement'])],
                    ['bi-balloon', 'Salles et événements', 'Salles de fêtes, de conférence et de réunion, espaces en plein air ou espaces complets.', 'Voir les salles', route('recherche', ['categorie' => 'salle'])],
                    ['bi-key', 'Gestion confiée à VIVI', 'Propriétaire absent ? Un gérant VIVI accueille vos clients et scanne les QR Codes.', 'Confier mon bien', route('proprietaires').'#gestion'],
                    ['bi-megaphone', 'Agents marketing', 'Un agent choisi par le propriétaire partage son lien et touche une commission sur les réservations.', 'En savoir plus', route('proprietaires').'#agents'],
                ] as [$icone, $nom, $texte, $lien, $url])
                    <div class="col-12 col-md-6 col-xl-3">
                        <a class="service-tile h-100" href="{{ $url }}">
                            <span class="service-tile__icon"><i class="bi {{ $icone }}" aria-hidden="true"></i></span>
                            <div class="d-flex flex-column align-self-stretch"><h3>{{ $nom }}</h3><p class="mb-3">{{ $texte }}</p><span class="btn btn-primary btn-sm mt-auto align-self-start">{{ $lien }}</span></div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===== Logements à la une ===== --}}
    @if ($aLaUne->isNotEmpty())
        <section class="section" aria-labelledby="titre-une">
            <div class="container">
                <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
                    <div class="section-head mb-0">
                        <span class="eyebrow">À la une</span>
                        <h2 id="titre-une">Des logements prêts à vous accueillir</h2>
                    </div>
                    <a class="btn btn-ghost" href="{{ route('recherche', ['categorie' => 'logement']) }}">Voir tous les logements</a>
                </div>
                <div class="row g-3 g-lg-4">
                    @foreach ($aLaUne as $l)
                        <div class="col-12 col-sm-6 col-lg-4">@include('partials.carte-logement', ['l' => $l])</div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ===== Publicité (espace « Accueil, entre les blocs ») ===== --}}
    <x-pub emplacement="accueil_milieu" conteneur />

    {{-- ===== Salles de fêtes et de conférence ===== --}}
    <section class="section bloc-salles" aria-labelledby="titre-salles">
        <div class="container">
            <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
                <div class="section-head mb-0">
                    <span class="eyebrow">Événements</span>
                    <h2 id="titre-salles">Salles de fêtes et de conférence</h2>
                    <p class="text-soft mb-0">Anniversaire, mariage, séminaire, réunion : réservez une salle à l’heure, à la demi-journée ou à la journée.</p>
                </div>
                <a class="btn btn-ghost" href="{{ route('recherche', ['categorie' => 'salle']) }}">Voir les salles</a>
            </div>
            @if ($salles->isNotEmpty())
                <div class="row g-3 g-lg-4">
                    @foreach ($salles as $l)
                        <div class="col-12 col-sm-6 col-lg-4">@include('partials.carte-logement', ['l' => $l])</div>
                    @endforeach
                </div>
            @else
                <div class="note-box"><i class="bi bi-balloon" aria-hidden="true"></i><span>Les premières salles arrivent bientôt. Vous avez une salle à louer ?
                    <a href="{{ route('connexion', ['mode' => 'inscription', 'role' => 'proprietaire']) }}">Publiez-la sur VIVI</a>.</span></div>
            @endif
        </div>
    </section>

    {{-- ===== Comment ça marche ===== --}}
    <section class="section bg-sand" id="comment-ca-marche" aria-labelledby="titre-comment">
        <div class="container">
            <div class="section-head">
                <span class="eyebrow">Comment ça marche</span>
                <h2 id="titre-comment">Réservé en quatre étapes</h2>
            </div>
            <ol class="steps-how">
                <li><h3>Cherchez</h3><p>Un logement ou une salle : indiquez la ville, la date et la formule (heure, demi-journée ou journée).</p></li>
                <li><h3>Composez</h3><p>Ajoutez les services dont vous avez besoin. Le total s’ajuste, commission VIVI comprise.</p></li>
                <li><h3>Payez</h3><p>Par Mobile Money ou carte bancaire. Votre créneau reste bloqué le temps du paiement.</p></li>
                <li><h3>Entrez</h3><p>Présentez votre QR Code à l’arrivée : il est scanné, et votre séjour ou votre événement commence.</p></li>
            </ol>
        </div>
    </section>

    {{-- ===== Services à la carte ===== --}}
    @if ($services->isNotEmpty())
        <section class="section" id="services" aria-labelledby="titre-services">
            <div class="container">
                <div class="section-head">
                    <span class="eyebrow">Services à la carte</span>
                    <h2 id="titre-services">Composez votre séjour ou votre événement selon votre budget</h2>
                    <p>Les prix sont fixés par VIVI : ils sont identiques dans tous les logements et toutes les salles qui proposent le service.</p>
                </div>
                <div class="row g-3">
                    @foreach ($services as $s)
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="service-tile">
                                <span class="service-tile__icon"><i class="bi {{ $s->icone ?: 'bi-bag-check' }}" aria-hidden="true"></i></span>
                                <div>
                                    <h3>{{ $s->nom }}</h3>
                                    @if ($s->description)<p>{{ $s->description }}</p>@endif
                                    <p class="tarif">{{ \App\Support\Montant::fcfa($s->prix) }} <span>{{ $s->unite }}</span></p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ===== Villes ===== --}}
    @if ($villesAvecNombre->isNotEmpty())
        <section class="section bg-sand" id="quartiers" aria-labelledby="titre-villes">
            <div class="container">
                <div class="section-head">
                    <span class="eyebrow">Où nous trouver</span>
                    <h2 id="titre-villes">Nos villes</h2>
                </div>
                <div class="place-grid">
                    @foreach ($villesAvecNombre as $ville => $nombre)
                        <a class="place-link" href="{{ route('recherche', ['ville' => $ville]) }}">
                            <span>{{ $ville }}<small>{{ collect([
                                $nombre['logements'] ? \App\Models\Reservation::pluriel($nombre['logements'], 'logement') : null,
                                $nombre['salles'] ? \App\Models\Reservation::pluriel($nombre['salles'], 'salle') : null,
                            ])->filter()->implode(' · ') }}</small></span>
                            <i class="bi bi-chevron-right" aria-hidden="true"></i>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ===== Application mobile (liens réglés dans les Paramètres) ===== --}}
    @if (\App\Support\ApplicationMobile::affichee())
        <section class="section pb-0" id="application" aria-labelledby="titre-application">
            <div class="container">
                <div class="app-band">
                    <div>
                        <span class="eyebrow">Application mobile</span>
                        <h2 id="titre-application">VIVI dans votre poche</h2>
                        <p class="lead-app">Réservez, payez et retrouvez votre QR Code d’entrée depuis votre téléphone, même au dernier moment.</p>
                        <ul class="app-band__points">
                            <li><i class="bi bi-bell" aria-hidden="true"></i>Rappels avant votre arrivée</li>
                            <li><i class="bi bi-qr-code" aria-hidden="true"></i>QR Code d’entrée toujours à portée de main</li>
                        </ul>
                    </div>
                    <div class="app-band__boutons">
                        <x-application />
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- ===== Propriétaires ===== --}}
    <section class="section" id="proprietaires" aria-labelledby="titre-proprietaires">
        <div class="container">
            <div class="owner-band">
                <div class="row g-0 align-items-stretch">
                    <div class="col-lg-7">
                        <div class="owner-band__body">
                            <span class="eyebrow" style="color:var(--vivi-orange-400)">Propriétaires</span>
                            <h2 id="titre-proprietaires">Publiez votre appartement ou votre salle sur VIVI</h2>
                            @php $avecFormules = \App\Services\ModeleEconomique::formulesProposees(); @endphp
                            <p class="mb-0">{{ $avecFormules ? 'Un abonnement' : 'Sans abonnement : VIVI garde '.\App\Services\Caisse::taux((float) \App\Support\Parametres::get('commission_retrait_taux')).' de vos retraits' }}, et vous gardez la main sur votre bien, ou vous nous en confiez la gestion.</p>
                            <ul>
                                <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>Vous fixez vos prix à l’heure, à la demi-journée et à la journée.</span></li>
                                <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>Vous définissez vos propres créneaux et bloquez les dates de votre choix.</span></li>
                                <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>La commission VIVI est payée par le client à la réservation, pas par vous.</span></li>
                                <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>Vous accueillez vous-même, avec votre gestionnaire, ou vous laissez VIVI gérer à votre place.</span></li>
                            </ul>
                            <div class="btn-duo">
                                <a class="btn btn-cta btn-lg" href="{{ route('connexion', ['mode' => 'inscription', 'role' => 'proprietaire']) }}">Publier mon bien</a>
                                <a class="btn btn-outline-light btn-lg" href="{{ route('proprietaires') }}#formules">{{ $avecFormules ? 'Voir les formules' : 'Voir les conditions' }}</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <img class="owner-band__img" src="{{ asset('images/proprietaires.webp') }}" alt="" width="1280" height="960" loading="lazy" decoding="async">
                    </div>
                </div>
            </div>
        </div>
    </section>

    @push('scripts')
        <script>
            // « Une salle » : le nombre de voyageurs devient une capacité (paliers de 20 à 500 places)
            (function () {
                var choix = document.querySelectorAll('input[name="categorie"]');
                var liste = document.getElementById('r-voyageurs');
                var libelle = document.querySelector('label[for="r-voyageurs"]');
                if (!choix.length || !liste) { return; }
                function regler() {
                    var salle = document.getElementById('r-cat-salle').checked;
                    libelle.textContent = salle ? libelle.dataset.libelleSalle : libelle.dataset.libelleLogement;
                    var premier = null;
                    Array.prototype.forEach.call(liste.options, function (o) {
                        var visible = o.dataset.pour === (salle ? 'salle' : 'logement');
                        o.hidden = !visible; o.disabled = !visible;
                        if (visible && premier === null) { premier = o; }
                    });
                    if (liste.selectedOptions[0] && liste.selectedOptions[0].disabled) {
                        liste.value = salle ? premier.value : '2';
                    }
                }
                choix.forEach(function (r) { r.addEventListener('change', regler); });
                regler();
            })();
        </script>
    @endpush
</x-layouts.public>
