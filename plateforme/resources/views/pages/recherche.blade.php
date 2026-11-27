{{-- Page « Logements » (maquette recherche.html). Le formulaire se soumet en GET : l'adresse peut être partagée. --}}
@php
    use App\Enums\Formule;
    use App\Enums\TypeLogement;
    use App\Models\Reservation;
    use App\Support\Montant;

    $c = $criteres;
    $f = $c['formule'];
    $fBudget = $f ?? Formule::Heure;   // le budget porte sur la formule choisie, à l'heure par défaut
    [$bMin, $bMax, $bPas] = $fBudget->budget($c['categorie'] === 'salle');   // une salle coûte plus cher qu'un logement
    $budget = $c['budget'] > 0 ? min($c['budget'], $bMax) : $bMax;
    // Paramètres repris dans le lien de chaque carte : la fiche s'ouvre sur la même date, formule et nombre de voyageurs
    $suite = ['date' => $c['date']->toDateString(), 'voyageurs' => $c['voyageurs'], 'formule' => $f?->value];
    // Catégorie : logements (appartements, chambres…) ou salles (fêtes, conférences) ; vide = tout
    $categorie = $c['categorie'];
    $salles = $categorie === 'salle';
    // Sans catégorie : « logements », ou « espaces » si des salles figurent dans les résultats
    $mot = match ($categorie) {
        'salle' => 'salle',
        'logement' => 'logement',
        default => $resultats->getCollection()->contains(fn ($l) => $l->estSalle()) ? 'espace' : 'logement',
    };
    $titre = ($resultats->total() ? Reservation::pluriel($resultats->total(), $mot) : 'Aucun'.($mot === 'salle' ? 'e salle' : ' '.$mot))
        .($c['ville'] ? ' à '.$c['ville'] : ($position && ! $horsRayon ? ' près de vous' : ' au Bénin'));
    $typesAffiches = $categorie ? TypeLogement::deCategorie($categorie) : TypeLogement::cases();
    // Liens des onglets : la recherche est gardée, seuls la catégorie et les types changent
    $lienCategorie = fn (?string $cat) => route('recherche', array_filter(array_merge(request()->except(['categorie', 'type', 'page']), ['categorie' => $cat]), fn ($v) => $v !== null && $v !== ''));
@endphp

<x-layouts.public page="{{ $salles ? 'salles' : 'recherche' }}" titre="{{ $salles ? 'Salles de fêtes et de conférence' : 'Logements' }}{{ $c['ville'] ? ' à '.$c['ville'] : '' }}"
    description="{{ $salles ? 'Salles de fêtes et de conférence' : 'Appartements meublés' }} à l’heure, à la demi-journée ou à la journée{{ $c['ville'] ? ' à '.$c['ville'] : ' au Bénin' }}.">

    <form method="GET" action="{{ route('recherche') }}" id="form-recherche">
        @if ($categorie)<input type="hidden" name="categorie" value="{{ $categorie }}">@endif
        {{-- ===== Rappel de la recherche ===== --}}
        <section class="search-bar" aria-label="Votre recherche">
            <div class="container">
                {{-- Logements ou salles --}}
                <nav class="onglets-categorie" aria-label="Type d’espace">
                    <a href="{{ $lienCategorie(null) }}" @if (! $categorie) aria-current="page" @endif><i class="bi bi-grid" aria-hidden="true"></i>Tout</a>
                    <a href="{{ $lienCategorie('logement') }}" @if ($categorie === 'logement') aria-current="page" @endif><i class="bi bi-house-door" aria-hidden="true"></i>Logements</a>
                    <a href="{{ $lienCategorie('salle') }}" @if ($salles) aria-current="page" @endif><i class="bi bi-balloon" aria-hidden="true"></i>Salles et événements</a>
                </nav>
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-4 col-lg-3">
                        <label class="form-label" for="b-ville">Ville</label>
                        <select class="form-select" id="b-ville" name="ville" data-auto>
                            <option value="">Toutes les villes</option>
                            @foreach ($villes as $v)
                                <option @selected($c['ville'] === $v)>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-7 col-md-3 col-lg-2">
                        <label class="form-label" for="b-date">Date</label>
                        <input class="form-control" type="date" id="b-date" name="date" min="{{ today()->toDateString() }}" value="{{ $c['date']->toDateString() }}" data-auto>
                    </div>
                    <div class="col-5 col-md-2">
                        <label class="form-label" for="b-voyageurs">{{ $salles ? 'Capacité' : 'Voyageurs' }}</label>
                        <select class="form-select" id="b-voyageurs" name="voyageurs" data-auto>
                            {{-- Salles : capacité par paliers ; logements : 1 à 6 voyageurs --}}
                            @foreach ($salles ? [1 => 'Toutes', 20 => '20 places et plus', 50 => '50 places et plus', 100 => '100 places et plus', 200 => '200 places et plus', 300 => '300 places et plus', 500 => '500 places et plus'] : array_combine(range(1, 6), range(1, 6)) as $n => $libelle)
                                <option value="{{ $n }}" @selected($c['voyageurs'] === $n)>{{ $libelle }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-3 col-lg-3">
                        {{-- Position du visiteur : gardée dans le formulaire tant qu'il ne choisit pas une ville --}}
                        @if ($position)
                            <input type="hidden" name="lat" value="{{ $position[0] }}">
                            <input type="hidden" name="lng" value="{{ $position[1] }}">
                            <a class="btn btn-ghost w-100 is-actif-geo" href="{{ route('recherche', array_filter(['categorie' => $categorie, 'date' => $c['date']->toDateString(), 'voyageurs' => $c['voyageurs']])) }}"
                               title="Ne plus utiliser ma position"><i class="bi bi-geo-alt-fill" aria-hidden="true"></i>Près de moi<i class="bi bi-x-lg ms-1" aria-hidden="true"></i></a>
                        @else
                            <button class="btn btn-ghost w-100" type="button" data-pres-de-moi><i class="bi bi-crosshair" aria-hidden="true"></i>Près de moi</button>
                        @endif
                    </div>
                    <div class="col-12 col-md-3 d-lg-none">
                        <button class="btn btn-ghost w-100" type="button" data-bs-toggle="offcanvas" data-bs-target="#filtres" aria-controls="filtres">
                            <i class="bi bi-sliders" aria-hidden="true"></i>Filtres
                            @if ($nombreFiltres)<span class="chip chip--count">{{ $nombreFiltres }}</span>@endif
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <div class="container py-4 py-lg-5">
            <div class="row g-4">

                {{-- ===== Filtres : colonne sur ordinateur, panneau latéral sur mobile ===== --}}
                <div class="col-lg-3">
                    <div class="offcanvas-lg offcanvas-end" tabindex="-1" id="filtres" aria-labelledby="filtres-titre">
                        <div class="offcanvas-header">
                            <h2 class="offcanvas-title h5 mb-0" id="filtres-titre">Filtres</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#filtres" aria-label="Fermer les filtres"></button>
                        </div>
                        <div class="offcanvas-body">
                            <div class="filter-panel w-100">
                                <div class="d-none d-lg-flex justify-content-between align-items-center mb-3">
                                    <h2 class="mb-0">Filtres</h2>
                                    <a class="btn btn-link btn-sm p-0" style="min-height:0" href="{{ route('recherche', array_filter(['categorie' => $categorie, 'ville' => $c['ville'], 'date' => $c['date']->toDateString(), 'voyageurs' => $c['voyageurs']])) }}">Réinitialiser</a>
                                </div>

                                <div class="filter-group filter-group--first" role="radiogroup" aria-labelledby="lg-formule">
                                    <h3 class="legend" id="lg-formule">Formule</h3>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="formule" id="ff-toutes" value="" @checked(! $f) data-auto><label class="form-check-label" for="ff-toutes">Toutes les formules</label></div>
                                    @foreach (Formule::cases() as $formule)
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="formule" id="ff-{{ $formule->value }}" value="{{ $formule->value }}" @checked($f === $formule) data-auto>
                                            <label class="form-check-label" for="ff-{{ $formule->value }}">{{ $formule->getLabel() }}</label>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="filter-group">
                                    {{-- Toujours utilisable : sans formule choisie, le budget porte sur le prix à l'heure (formule cochée d'office) --}}
                                    <label class="legend d-block" for="f-budget">Budget maximum <span class="fw-normal text-soft">({{ $fBudget->par() }})</span></label>
                                    <input class="form-range" type="range" id="f-budget" name="budget" min="{{ $bMin }}" max="{{ $bMax }}" step="{{ $bPas }}" value="{{ $budget }}"
                                           data-auto-fin @unless ($f) data-formule-defaut="{{ $fBudget->value }}" @endunless
                                           aria-describedby="f-budget-texte">
                                    <div class="d-flex justify-content-between form-text mt-0" aria-hidden="true"><span>{{ Montant::fcfa($bMin) }}</span><span>{{ Montant::fcfa($bMax) }}+</span></div>
                                    <p class="form-text mb-0 fw-semibold text-ink" id="f-budget-texte" data-max="{{ $bMax }}" data-unite="{{ $fBudget->par() }}">
                                        {{ $budget >= $bMax ? 'Tous les prix' : 'Jusqu’à '.Montant::fcfa($budget).' '.$fBudget->par() }}
                                    </p>
                                    @unless ($f)<p class="form-text mb-0">Choisissez une formule pour un budget à la demi-journée ou à la journée.</p>@endunless
                                </div>

                                <div class="filter-group" role="group" aria-labelledby="lg-type">
                                    <h3 class="legend" id="lg-type">{{ $salles ? 'Type de salle' : 'Type d’espace' }}</h3>
                                    @foreach ($typesAffiches as $type)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="type[]" id="ft-{{ $type->value }}" value="{{ $type->value }}" @checked(in_array($type->value, $c['types'], true)) data-auto>
                                            <label class="form-check-label" for="ft-{{ $type->value }}">{{ $type->getLabel() }}</label>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="filter-group" role="group" aria-labelledby="lg-equip">
                                    <h3 class="legend" id="lg-equip">Équipements</h3>
                                    @foreach ($listeEquipements as $e)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="equipement[]" id="fe-{{ $e->id }}" value="{{ $e->id }}" @checked(in_array($e->id, $c['equipements'], true)) data-auto>
                                            <label class="form-check-label" for="fe-{{ $e->id }}">{{ $e->nom }}</label>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="filter-group" role="group" aria-labelledby="lg-services">
                                    <h3 class="legend" id="lg-services">Services à la carte</h3>
                                    @foreach ($listeServices as $s)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="service[]" id="fs-{{ $s->id }}" value="{{ $s->id }}" @checked(in_array($s->id, $c['services'], true)) data-auto>
                                            <label class="form-check-label" for="fs-{{ $s->id }}">{{ $s->nom }}</label>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="d-flex d-lg-none gap-2 pt-3">
                                    <a class="btn btn-ghost flex-fill" href="{{ route('recherche', array_filter(['categorie' => $categorie, 'ville' => $c['ville'], 'date' => $c['date']->toDateString(), 'voyageurs' => $c['voyageurs']])) }}">Réinitialiser</a>
                                    <button class="btn btn-primary flex-fill" type="submit">{{ $resultats->total() ? 'Voir '.Reservation::pluriel($resultats->total(), $mot) : ($salles ? 'Aucune salle' : 'Aucun résultat') }}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ===== Résultats ===== --}}
                <div class="col-lg-9">
                    <div class="results-head">
                        <div>
                            <h1 id="titre-resultats">{{ $titre }}</h1>
                            <p class="text-soft mb-0">
                                {{ ucfirst($c['date']->translatedFormat('D j M')) }} · {{ $f ? mb_strtolower($f->getLabel()) : 'toutes formules' }} · {{ $salles ? ($c['voyageurs'] > 1 ? $c['voyageurs'].' places et plus' : 'toutes capacités') : Reservation::pluriel($c['voyageurs'], 'voyageur') }}
                            </p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <label class="form-label mb-0 text-nowrap" for="tri">Trier par</label>
                            <select class="form-select" id="tri" name="tri" style="min-width:11.5rem" data-auto>
                                <option value="pertinence" @selected($c['tri'] === 'pertinence')>Pertinence</option>
                                <option value="prix" @selected($c['tri'] === 'prix')>Prix croissant</option>
                                <option value="note" @selected($c['tri'] === 'note')>Mieux notés</option>
                                @if ($position)<option value="distance" @selected($c['tri'] === 'distance')>Les plus proches</option>@endif
                            </select>
                        </div>
                    </div>
                    @if ($position && $horsRayon && ! $c['ville'])
                        <div class="note-box mb-3" role="status"><i class="bi bi-geo-alt" aria-hidden="true"></i>
                            <span>{{ $salles ? 'Aucune salle disponible' : 'Aucun logement disponible' }} à moins de {{ $rayonKm }} km de vous : voici les plus proches, du plus près au plus loin.</span></div>
                    @endif

                    <div class="d-grid gap-3" id="resultats">
                        @forelse ($resultats as $l)
                            @php
                                $quand = $c['date']->isToday() ? 'aujourd’hui' : 'le '.$c['date']->translatedFormat('j M');
                            @endphp
                            @include('partials.carte-logement', [
                                'l' => $l, 'ligne' => true, 'niveau' => 2, 'formule' => $f, 'suite' => $suite,
                                'dispo' => 'Disponible '.$quand,
                            ])
                            {{-- Publicité tous les N résultats (Administration › Paramètres) ; moins de N résultats : une seule, à la fin --}}
                            @php $tous = max(1, (int) \App\Support\Parametres::get('pub_frequence_liste')); @endphp
                            @if (($loop->iteration % $tous === 0 && ! $loop->last) || ($loop->last && $loop->count < $tous))
                                <x-pub emplacement="recherche" :ville="$c['ville']" />
                            @endif
                        @empty
                            <div class="empty-state">
                                <i class="bi bi-search" aria-hidden="true"></i>
                                <h2 class="h4 mt-2">{{ $salles ? 'Aucune salle ne correspond' : 'Aucun logement ne correspond' }} à ces critères</h2>
                                <p class="text-soft">Changez de date, augmentez le budget, retirez un filtre ou choisissez une autre ville.</p>
                                <a class="btn btn-primary" href="{{ route('recherche', array_filter(['categorie' => $categorie])) }}">Réinitialiser la recherche</a>
                            </div>
                        @endforelse
                    </div>

                    @if ($resultats->hasPages())
                        <nav class="mt-4" aria-label="Pages de résultats">{{ $resultats->links() }}</nav>
                    @endif
                </div>

            </div>
        </div>
    </form>

    @push('scripts')
        <script>
            // Filtres appliqués dès qu'on les change (sur ordinateur) ; sur mobile, bouton « Voir les logements »
            (function () {
                var form = document.getElementById('form-recherche');
                var mobile = function () { return window.matchMedia('(max-width: 991.98px)').matches; };
                function envoyer() {
                    // Pas de paramètres vides dans l'adresse
                    [].forEach.call(form.elements, function (el) { if (el.name && el.value === '' && el.type !== 'radio') { el.disabled = true; } });
                    var budget = document.getElementById('f-budget');
                    if (budget && !budget.disabled && +budget.value >= +budget.max) { budget.disabled = true; }
                    // Budget choisi sans formule : la formule du budget (à l'heure) est cochée pour que le filtre s'applique
                    if (budget && !budget.disabled && budget.dataset.formuleDefaut) {
                        var radio = document.getElementById('ff-' + budget.dataset.formuleDefaut);
                        if (radio) { radio.checked = true; radio.disabled = false; }
                    }
                    window.viviBarre && window.viviBarre();
                    form.submit();
                }
                form.addEventListener('change', function (e) {
                    var dansFiltres = e.target.closest('#filtres');
                    if (e.target.matches('[data-auto], [data-auto-fin]') && !(dansFiltres && mobile())) { envoyer(); }
                });
                form.addEventListener('submit', function (e) { e.preventDefault(); envoyer(); });
                var budget = document.getElementById('f-budget'), texte = document.getElementById('f-budget-texte');
                if (budget && texte) {
                    budget.addEventListener('input', function () {
                        var v = +budget.value;
                        texte.textContent = v >= +texte.dataset.max ? 'Tous les prix'
                            : 'Jusqu’à ' + new Intl.NumberFormat('fr-FR').format(v).replace(/\s/g, ' ') + ' FCFA ' + texte.dataset.unite;
                    });
                }
            })();
        </script>
    @endpush
</x-layouts.public>
