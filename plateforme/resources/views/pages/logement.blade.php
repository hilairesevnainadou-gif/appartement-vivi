{{-- Fiche logement (maquette logement.html). Le bloc « Réserver » est rempli à partir de l'état calculé par le serveur
     (SimulationSejour) et se met à jour en interrogeant route('logement.disponibilites') à chaque changement. --}}
@php
    use App\Enums\Formule;
    use App\Models\Reservation;
    use App\Support\Montant;
    use App\Support\Parametres;

    $l = $logement;
    $photos = $l->photos->take(5);
    $type = \Illuminate\Support\Str::before($l->type->getLabel(), ' (');
    $delaiReport = Parametres::get('report_delai_minimum_heures');
    $fenetreReport = Parametres::get('report_fenetre_jours');
    $formuleInitiale = $etat['formules'] ? collect($etat['formules'])->firstWhere('valeur', $etat['formule']) : null;
@endphp

<x-layouts.public page="logement" :titre="$l->titre.' · '.$l->quartier.', '.$l->ville" :nav-basse="false" classe-corps="has-bookbar"
    :description="($l->estSalle() ? $l->type->getLabel() : 'Appartement meublé').' à '.$l->ville.', '.$l->quartier.' : '.implode(', ', array_map(fn ($f) => mb_strtolower($f->getLabel()), $l->formulesProposees())).'. Réservation en ligne, entrée par QR Code.'">

    <div class="pb-5">
        <div class="container pt-3 pt-lg-4">

            @if ($apercu)
                <div class="note-box note-box--orange mb-3" role="note">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                    <span>Aperçu : {{ $l->mot('ce') }} n’est pas visible des clients ({{ mb_strtolower($l->statut->getLabel()) }}{{ $l->en_ligne ? '' : ', en pause' }}).</span>
                </div>
            @endif

            <nav aria-label="Fil d’Ariane">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('accueil') }}">Accueil</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('recherche', ['categorie' => $l->categorie()]) }}">{{ $l->estSalle() ? 'Salles et événements' : 'Logements' }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('recherche', ['categorie' => $l->categorie(), 'ville' => $l->ville]) }}">{{ $l->ville }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $l->titre }}</li>
                </ol>
            </nav>

            {{-- ===== Titre ===== --}}
            <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
                <div>
                    <h1 class="h2 mb-2">{{ $l->titre }}</h1>
                    <div class="d-flex flex-wrap align-items-center gap-2 gap-md-3">
                        @if ($l->noteAffichee())
                            <span class="rating"><i class="bi bi-star-fill" aria-hidden="true"></i>{{ $l->noteAffichee() }} <small>({{ $l->nombre_avis }} avis)</small></span>
                        @else
                            <span class="chip chip--line">Nouveau sur VIVI</span>
                        @endif
                        <span class="text-soft"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ $l->quartier }}, {{ $l->ville }}</span>
                        <span class="chip chip--teal"><i class="bi bi-patch-check-fill" aria-hidden="true"></i>Vérifié par VIVI</span>
                    </div>
                </div>
            </div>

            {{-- ===== Photos ===== --}}
            @if ($photos->isNotEmpty())
                <div class="gallery-wrap mb-4">
                    <div class="gallery" data-count="{{ $photos->count() }}" aria-label="Photos {{ $l->mot('du') }}">
                        @foreach ($photos as $i => $photo)
                            <div class="gallery__item">
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($photo->chemin) }}"
                                     alt="{{ $photo->legende ?: $l->titre.', photo '.($i + 1).' sur '.$photos->count() }}"
                                     width="{{ $i === 0 ? 1280 : 640 }}" height="{{ $i === 0 ? 960 : 480 }}"
                                     @if ($i === 0) fetchpriority="high" @else loading="lazy" @endif decoding="async">
                            </div>
                        @endforeach
                    </div>
                    @if ($l->photos->count() > 1)
                        <a class="btn btn-ghost btn-sm gallery__more d-none d-md-inline-flex" href="#" data-bientot="Visionneuse de photos" style="background:#fff"><i class="bi bi-images" aria-hidden="true"></i>Voir toutes les photos</a>
                    @endif
                </div>
            @endif

            <ul class="facts mb-4">
                @if ($l->estSalle())
                    {{-- Salle de fêtes ou de conférence : capacité en personnes, ni chambres ni lits --}}
                    <li><i class="bi bi-balloon" aria-hidden="true"></i>{{ $type }}</li>
                    <li><i class="bi bi-people" aria-hidden="true"></i>Capacité : {{ $l->personnes() }}</li>
                    @if ($l->salles_de_bain > 0)<li><i class="bi bi-droplet" aria-hidden="true"></i>{{ Reservation::pluriel($l->salles_de_bain, 'toilette') }}</li>@endif
                @else
                    <li><i class="bi bi-house-door" aria-hidden="true"></i>{{ $type }}</li>
                    <li><i class="bi bi-people" aria-hidden="true"></i>{{ $l->personnes() }}</li>
                    <li><i class="bi bi-door-closed" aria-hidden="true"></i>{{ Reservation::pluriel($l->chambres, 'chambre') }}</li>
                    @if ($l->unites > 1)<li><i class="bi bi-buildings" aria-hidden="true"></i>Résidence : {{ $l->unites }} chambres de ce type</li>@endif
                    <li><i class="bi bi-moon" aria-hidden="true"></i>{{ Reservation::pluriel($l->lits, 'lit') }}</li>
                    <li><i class="bi bi-droplet" aria-hidden="true"></i>{{ $l->salles_de_bain }} {{ $l->salles_de_bain > 1 ? 'salles de bain' : 'salle de bain' }}</li>
                @endif
            </ul>

            <div class="fiche-grid">

                {{-- ===== A. Présentation et tarifs ===== --}}
                <div class="fiche-a">
                    <section class="block" aria-labelledby="t-description">
                        <h2 id="t-description">{{ ucfirst($l->mot('le')) }}</h2>
                        @foreach (preg_split('/\R{2,}/', trim((string) $l->description)) as $paragraphe)
                            @if (filled($paragraphe))<p>{!! nl2br(e($paragraphe)) !!}</p>@endif
                        @endforeach
                        <p class="mb-0">{{ $l->estGereParVivi() ? 'L’équipe VIVI' : 'Le propriétaire ou son gestionnaire' }} vous accueille sur place, scanne votre QR Code et vous remet {{ $l->estSalle() ? $l->mot('le') : 'les clés' }}.</p>
                    </section>

                    <section class="block" aria-labelledby="t-tarifs">
                        <h2 id="t-tarifs">Tarifs et créneaux</h2>
                        <div class="tarifs">
                            @foreach (Formule::cases() as $f)
                                @php $t = $l->tarif($f); @endphp
                                <div class="tarif-row{{ $t ? '' : ' is-off' }}">
                                    <h3>{{ $f->getLabel() }}</h3>
                                    <div class="price">
                                        @if ($t)<strong>{{ Montant::fcfa($t->prix) }}</strong> <span>{{ $f->unite() }}</span>@else<span>Non proposé</span>@endif
                                    </div>
                                    <p>
                                        @if (! $t)
                                            Le propriétaire ne propose pas cette formule.
                                        @elseif ($f === Formule::Heure)
                                            {{ $t->horairesEnClair() }} · minimum {{ $t->duree_minimum_heures }} h
                                        @elseif ($f === Formule::DemiJournee)
                                            {{ $l->creneaux->sortBy('ordre')->map(fn ($c) => $c->nom.' '.substr($c->heure_debut, 0, 5).'–'.substr($c->heure_fin, 0, 5))->implode(' · ') }}
                                        @else
                                            {{ $l->estSalle()
                                                ? 'De '.substr($t->heure_arrivee, 0, 5).' à '.substr($t->heure_depart, 0, 5).($t->heure_depart <= $t->heure_arrivee ? ' (après minuit)' : ', le même jour')
                                                : 'Arrivée à partir de '.substr($t->heure_arrivee, 0, 5).' · départ avant '.substr($t->heure_depart, 0, 5).' le lendemain' }}
                                        @endif
                                    </p>
                                </div>
                            @endforeach
                        </div>
                        <div class="note-box mt-3">
                            <i class="bi bi-info-circle" aria-hidden="true"></i>
                            <span>Les formules, les tarifs et les créneaux sont fixés par le propriétaire. Ils peuvent être différents d’{{ $l->mot() === 'salle' ? 'une salle' : 'un '.$l->mot() }} à l’autre.</span>
                        </div>
                    </section>
                </div>

                {{-- ===== W. Réservation ===== --}}
                <div class="fiche-w">
                    {{-- Bloc « Réserver », comme la feuille de l'application : feuille qui monte du bas sur mobile, carte à droite sur grand écran --}}
                    <div class="booking-sheet offcanvas-lg offcanvas-bottom" tabindex="-1" id="reserver" aria-labelledby="t-reserver">
                        <div class="offcanvas-header">
                            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#reserver" aria-label="Fermer"></button>
                        </div>
                        <div class="offcanvas-body">
                    <aside class="booking-card">
                        <h2 class="booking-card__titre" id="t-reserver">Réserver</h2>
                        <p class="booking-card__sous">{{ $l->titre }}</p>

                        @error('reservation')
                            <div class="note-box note-box--orange mb-3" role="alert"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>{{ $message }}</span></div>
                        @enderror
                        <form id="form-reservation" novalidate data-url="{{ route('logement.disponibilites', $l->slug) }}"
                              data-reserver="{{ route('reservation.bloquer', $l->slug) }}" data-connexion="{{ route('connexion') }}" data-connecte="{{ auth()->check() ? '1' : '0' }}">
                            {{-- Formules proposées seulement ; aucun choix s'il n'y en a qu'une --}}
                            <div class="field" @if (count($l->formulesProposees()) < 2) hidden @endif>
                                <div class="segmented" role="radiogroup" aria-label="Formule">
                                    @foreach ($l->formulesProposees() as $f)
                                        <input type="radio" name="formule" id="w-{{ $f->value }}" value="{{ $f->value }}" @checked($etat['formule'] === $f->value)>
                                        <label for="w-{{ $f->value }}">{{ $f->court() }}</label>
                                    @endforeach
                                </div>
                            </div>

                            <div class="booking-ligne">
                                <label class="booking-date" for="w-date">
                                    <small>{{ $l->estSalle() ? 'Date de l’événement' : 'Date d’arrivée' }}</small>
                                    <strong id="w-date-texte">{{ $etat['date_texte'] }}</strong>
                                    <input type="date" id="w-date" name="date" min="{{ $etat['date_min'] }}" value="{{ $etat['date'] }}">
                                </label>
                                {{-- Nombre de voyageurs : logements seulement (une salle se réserve entière) --}}
                                @unless ($l->estSalle())
                                    <div class="text-end">
                                        <small class="d-block text-soft" id="lbl-voyageurs">Voyageurs</small>
                                        <div class="stepper" id="c-voyageurs" role="group" aria-labelledby="lbl-voyageurs" data-nom="voyageurs" data-suffixe="">
                                            <button type="button" data-pas="-1" aria-label="Un voyageur de moins"><i class="bi bi-dash-lg" aria-hidden="true"></i></button>
                                            <output aria-live="polite"></output>
                                            <button type="button" data-pas="1" aria-label="Un voyageur de plus"><i class="bi bi-plus-lg" aria-hidden="true"></i></button>
                                        </div>
                                    </div>
                                @endunless
                            </div>

                            {{-- Plus rien de libre aujourd'hui : la date est avancée au premier jour libre --}}
                            <div class="note-box mb-3" id="w-propose" role="status" hidden><i class="bi bi-info-circle" aria-hidden="true"></i><span></span></div>

                            {{-- Formule « heure » --}}
                            <fieldset class="field" data-formule="heure" hidden>
                                <legend class="form-label">{{ $l->estSalle() ? 'Heure de début' : 'Heure d’arrivée' }}</legend>
                                <div class="choix-grille" id="w-heures"></div>
                                <div class="field-row mt-3" id="w-ligne-duree">
                                    <span class="form-label" id="lbl-duree">Durée</span>
                                    <div class="stepper" id="c-duree" role="group" aria-labelledby="lbl-duree" data-nom="duree" data-suffixe=" h">
                                        <button type="button" data-pas="-1" aria-label="Une heure de moins"><i class="bi bi-dash-lg" aria-hidden="true"></i></button>
                                        <output aria-live="polite"></output>
                                        <button type="button" data-pas="1" aria-label="Une heure de plus"><i class="bi bi-plus-lg" aria-hidden="true"></i></button>
                                    </div>
                                </div>
                            </fieldset>

                            {{-- Formule « demi-journée » --}}
                            <fieldset class="field" data-formule="demi_journee" hidden>
                                <legend class="form-label">Créneau</legend>
                                <div class="choix-liste" id="w-creneaux"></div>
                            </fieldset>

                            {{-- Formule « journée » --}}
                            <div class="field" data-formule="journee" hidden>
                                <div class="field-row">
                                    <span class="form-label" id="lbl-jours">Nombre de jours</span>
                                    <div class="stepper" id="c-jours" role="group" aria-labelledby="lbl-jours" data-nom="jours" data-suffixe="">
                                        <button type="button" data-pas="-1" aria-label="Un jour de moins"><i class="bi bi-dash-lg" aria-hidden="true"></i></button>
                                        <output aria-live="polite"></output>
                                        <button type="button" data-pas="1" aria-label="Un jour de plus"><i class="bi bi-plus-lg" aria-hidden="true"></i></button>
                                    </div>
                                </div>
                            </div>

                            <p class="form-text mb-0 mt-n2" id="w-aide"></p>

                            <div class="note-box note-box--orange mt-3" id="w-message" role="status" hidden><i class="bi bi-info-circle" aria-hidden="true"></i><span></span></div>
                            <div id="w-devis" hidden>
                                <hr class="booking-separateur">
                                <dl class="price-lines" id="w-lignes" aria-live="polite"></dl>
                            </div>

                            <button class="btn btn-cta btn-lg w-100 mt-3" type="submit" id="w-continuer">{{ auth()->check() ? 'Continuer' : 'Me connecter pour réserver' }}</button>
                        </form>

                        <ul class="reassure d-none d-lg-grid">
                            <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>Aucun paiement à cette étape.</span></li>
                            <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>Entrée par QR Code à votre arrivée.</span></li>
                            <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>Report gratuit jusqu’à {{ $delaiReport }} h avant l’arrivée.</span></li>
                        </ul>
                    </aside>
                        </div>
                    </div>
                </div>

                {{-- ===== B. Détails ===== --}}
                <div class="fiche-b">
                    @if ($l->equipements->isNotEmpty())
                        <section class="block" aria-labelledby="t-equipements">
                            <h2 id="t-equipements">Équipements</h2>
                            <ul class="amenities">
                                @foreach ($l->equipements as $e)
                                    <li><i class="bi {{ $e->icone ?: 'bi-check2' }}" aria-hidden="true"></i>{{ $e->nom }}</li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    @if ($l->services->isNotEmpty())
                        <section class="block" aria-labelledby="t-services">
                            <h2 id="t-services">Services à la carte</h2>
                            <p class="text-soft">À ajouter au moment de réserver. Les prix sont fixés par VIVI.</p>
                            <div class="row g-3">
                                @foreach ($l->services as $s)
                                    <div class="col-md-6">
                                        <div class="service-tile">
                                            <span class="service-tile__icon"><i class="bi {{ $s->icone ?: 'bi-bag-check' }}" aria-hidden="true"></i></span>
                                            <div>
                                                <h3>{{ $s->nom }}</h3>
                                                @if ($s->description)<p>{{ $s->description }}</p>@endif
                                                <p class="tarif">{{ Montant::fcfa($s->prix) }} <span>{{ $s->unite }}</span></p>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    <section class="block" aria-labelledby="t-regles">
                        <h2 id="t-regles">{{ $l->estSalle() ? 'Règles '.$l->mot('du').'' : 'Règles du logement' }}</h2>
                        <ul class="rules">
                            <li><i class="bi bi-people" aria-hidden="true"></i><span>{{ $l->estSalle() ? 'Capacité maximale : '.$l->personnes().'.' : $l->personnes().' au maximum.' }}</span></li>
                            @if ($l->estSalle())
                                <li><i class="bi bi-clock" aria-hidden="true"></i><span>Installation et rangement compris dans le temps réservé.</span></li>
                                <li><i class="bi bi-volume-up" aria-hidden="true"></i><span>Musique et sonorisation dans le respect du voisinage et des horaires.</span></li>
                            @else
                                <li><i class="bi bi-slash-circle" aria-hidden="true"></i><span>Logement non-fumeur.</span></li>
                                <li><i class="bi bi-volume-mute" aria-hidden="true"></i><span>Pas de fête ni d’événement sans l’accord du propriétaire.</span></li>
                            @endif
                            <li><i class="bi bi-clock" aria-hidden="true"></i><span>{{ $l->estSalle() ? ucfirst($l->mot('le')).' se libère à l’heure prévue, pour la préparation du prochain événement.' : 'Le départ se fait à l’heure prévue : le logement est préparé pour le client suivant.' }}</span></li>
                        </ul>
                    </section>

                    <section class="block" aria-labelledby="t-annulation">
                        <h2 id="t-annulation">Annulation et report</h2>
                        <ul class="rules mb-3">
                            <li><i class="bi bi-hourglass-split" aria-hidden="true"></i><span><strong>Heure et demi-journée :</strong> location et services remboursés jusqu’à 24 h avant l’arrivée ; ensuite, seuls les services.</span></li>
                            <li><i class="bi bi-calendar-x" aria-hidden="true"></i><span><strong>Journée :</strong> location et services remboursés jusqu’à 5 jours avant l’arrivée ; ensuite, la première journée et la moitié des suivantes restent dues.</span></li>
                            <li><i class="bi bi-receipt" aria-hidden="true"></i><span><strong>Commission VIVI :</strong> elle n’est jamais remboursée.</span></li>
                            <li><i class="bi bi-arrow-repeat" aria-hidden="true"></i><span><strong>Un empêchement ?</strong> Vous pouvez reporter votre réservation jusqu’à {{ $delaiReport }} h avant l’arrivée, vers une date libre dans les {{ $fenetreReport }} jours. Passé ce délai, la réservation est considérée comme consommée.</span></li>
                        </ul>
                        <a href="{{ route('aide.annulation') }}">Lire les conditions complètes</a>
                    </section>

                    <section class="block" aria-labelledby="t-emplacement">
                        <h2 id="t-emplacement">Emplacement</h2>
                        @if ($zone = $l->positionApprochee())
                            @push('head')
                                @vite('resources/js/carte.js')
                            @endpush
                            <div class="carte carte--zone" data-carte-zone data-lat="{{ $zone[0] }}" data-lng="{{ $zone[1] }}" role="img"
                                 aria-label="Carte : {{ $l->mot('le') }} se trouve dans la zone indiquée, à {{ $l->quartier }}, {{ $l->ville }}"></div>
                            <p class="small text-soft mt-2 mb-0"><i class="bi bi-geo-alt-fill me-1" aria-hidden="true"></i><strong class="text-ink">{{ $l->quartier }}, {{ $l->ville }}</strong> · zone approximative. L’adresse exacte et l’itinéraire vous sont envoyés après le paiement.</p>
                        @else
                        <div class="map-placeholder">
                            <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                            <strong>{{ $l->quartier }}, {{ $l->ville }}</strong>
                            <span class="small">L’adresse exacte et l’itinéraire vous sont envoyés après le paiement.</span>
                        </div>
                        @endif
                    </section>

                    <section class="block pb-0" aria-labelledby="t-avis">
                        @if ($avis->isEmpty())
                            <h2 id="t-avis">Avis</h2>
                            <p class="text-soft mb-0">Pas encore d’avis : {{ $l->mot('ce') }} vient d’arriver sur VIVI. Les avis sont laissés par des clients qui {{ $l->estSalle() ? 'l’ont réservé'.($l->mot() === 'salle' ? 'e' : '') : 'y ont séjourné' }}.</p>
                        @else
                            <h2 id="t-avis"><span class="rating"><i class="bi bi-star-fill" aria-hidden="true"></i>{{ $l->noteAffichee() }}</span> · {{ $l->nombre_avis }} avis</h2>
                            <div class="row g-3">
                                @foreach ($avis as $a)
                                    @php
                                        $mots = $a->client?->name === \App\Services\SuppressionCompte::NOM ? ['Ancien client'] : preg_split('/\s+/', trim($a->client?->name ?? 'Client VIVI'));
                                        $nom = $mots[0].(isset($mots[1]) ? ' '.mb_substr($mots[1], 0, 1).'.' : '');
                                        $initiales = mb_strtoupper(mb_substr($mots[0], 0, 1).(isset($mots[1]) ? mb_substr($mots[1], 0, 1) : ''));
                                    @endphp
                                    <div class="col-md-6">
                                        <figure class="review mb-0">
                                            <div class="stars" aria-label="{{ $a->note }} sur 5">
                                                @for ($i = 1; $i <= 5; $i++)<i class="bi {{ $i <= $a->note ? 'bi-star-fill' : 'bi-star' }}" aria-hidden="true"></i>@endfor
                                            </div>
                                            @if ($a->commentaire)<blockquote>« {{ $a->commentaire }} »</blockquote>@endif
                                            <figcaption class="review__who">
                                                <span class="avatar" aria-hidden="true">{{ $initiales }}</span>
                                                <span><strong>{{ $nom }}</strong><small>{{ ($l->estSalle() ? 'Réservation ' : 'Séjour ').($a->reservation ? mb_strtolower($a->reservation->formule->getLabel()) : 'VIVI') }} · {{ $a->created_at->translatedFormat('F Y') }}</small></span>
                                            </figcaption>
                                        </figure>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </section>
                </div>

            </div>
        </div>
    </div>

    @push('apres-contenu')
        {{-- Barre basse (mobile) : prix et accès au bloc de réservation --}}
        <div class="bookbar" id="barre-reserver">
            <div class="price"><small>À partir de</small><strong id="bb-prix">{{ $formuleInitiale['prix_texte'] ?? '—' }}</strong> <span id="bb-unite">{{ $formuleInitiale['unite'] ?? '' }}</span></div>
            <button class="btn btn-cta" type="button" data-bs-toggle="offcanvas" data-bs-target="#reserver" aria-controls="reserver">Réserver</button>
        </div>
    @endpush

    @push('scripts')
        <script type="application/json" id="etat-initial">@json($etat)</script>
        <script>
            /* Bloc « Réserver » : l'état (disponibilités et prix) vient toujours du serveur. Même comportement que l'application. */
            (function () {
                var form = document.getElementById('form-reservation');
                var $ = function (id) { return document.getElementById(id); };
                var etat = JSON.parse($('etat-initial').textContent);
                var jourPropose = null;     // date avancée automatiquement (plus rien de libre aujourd'hui)
                var JOURS_CHERCHES = 7;
                var echapper = function (t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; };
                var fcfa = function (n) { return new Intl.NumberFormat('fr-FR').format(n).replace(/ | /g, ' ') + ' FCFA'; };
                var jourLong = function (iso) {
                    var d = new Date(iso + 'T12:00:00');
                    var t = d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
                    return t.charAt(0).toUpperCase() + t.slice(1);
                };

                function complet(e) {
                    if (e.formule === 'heure') { return !e.heures.some(function (h) { return h.libre; }); }
                    if (e.formule === 'demi_journee') { return !e.creneaux.some(function (c) { return c.libre; }); }
                    if (e.formule === 'journee') { return !e.jours_max; }
                    return false;
                }

                function stepper(id, valeur, min, max, format) {
                    var el = $(id), moins = el.querySelector('[data-pas="-1"]'), plus = el.querySelector('[data-pas="1"]');
                    el.dataset.valeur = valeur; el.dataset.min = min; el.dataset.max = max;
                    el.querySelector('output').textContent = format ? format(valeur) : valeur;
                    moins.disabled = valeur <= min; plus.disabled = valeur >= max;
                }

                function afficher() {
                    var formule = etat.formule;
                    [].forEach.call(form.querySelectorAll('[name="formule"]'), function (r) { r.checked = r.value === formule; });
                    form.querySelectorAll('[data-formule]').forEach(function (b) { b.hidden = b.dataset.formule !== formule; });
                    $('w-date').value = etat.date;
                    $('w-date-texte').textContent = jourLong(etat.date);

                    var f = (etat.formules || []).filter(function (x) { return x.valeur === formule; })[0];
                    if (f) { $('bb-prix').textContent = f.prix_texte; $('bb-unite').textContent = f.unite; }

                    // Date avancée : on le dit, comme dans l'application
                    var propose = $('w-propose');
                    propose.hidden = etat.date !== jourPropose;
                    if (!propose.hidden) {
                        var demain = new Date(etat.date_min + 'T12:00:00'); demain.setDate(demain.getDate() + 1);
                        var quand = (demain.toISOString().slice(0, 10) === etat.date ? 'demain, ' : '') + jourLong(etat.date).toLowerCase();
                        propose.querySelector('span').textContent = formule === 'heure' ? 'Plus d’heure libre aujourd’hui. Voici les heures disponibles ' + quand + '.'
                            : formule === 'demi_journee' ? 'Plus de créneau libre aujourd’hui. Voici les créneaux disponibles ' + quand + '.'
                            : 'Plus de journée libre aujourd’hui. Prochaine date disponible : ' + quand + '.';
                    }

                    // Heure : cases ; une heure prise est barrée
                    $('w-heures').innerHTML = etat.heures.map(function (h, i) {
                        return '<div class="choix"><input type="radio" name="heure" id="h-' + i + '" value="' + h.heure + '"' +
                            (h.libre ? '' : ' disabled') + (h.heure === etat.heure ? ' checked' : '') + '>' +
                            '<label for="h-' + i + '"' + (h.libre ? '' : ' title="Déjà pris"') + '>' + h.heure + '</label></div>';
                    }).join('');
                    $('w-ligne-duree').hidden = !(etat.heure && etat.duree);
                    if (etat.duree) { stepper('c-duree', etat.duree, etat.duree_min, etat.duree_max, function (n) { return n + ' h'; }); }

                    // Demi-journée : « Matin · 06:00 – 11:00 », barré s'il est complet
                    $('w-creneaux').innerHTML = etat.creneaux.map(function (c) {
                        return '<div class="choix"><input type="radio" name="creneau_id" id="cr-' + c.id + '" value="' + c.id + '"' +
                            (c.libre ? '' : ' disabled') + (c.id === etat.creneau_id ? ' checked' : '') + '>' +
                            '<label for="cr-' + c.id + '">' + echapper(c.nom) + ' · ' + c.de + ' – ' + c.a + '</label></div>';
                    }).join('');

                    if (etat.jours) { stepper('c-jours', etat.jours, 1, etat.jours_max); }
                    if ($('c-voyageurs')) { stepper('c-voyageurs', etat.voyageurs, 1, etat.capacite); }   // absent pour une salle

                    $('w-aide').textContent = etat.aide || '';
                    $('w-aide').hidden = !etat.aide;
                    var message = $('w-message');
                    message.hidden = !etat.message;
                    message.querySelector('span').textContent = etat.message || '';

                    var d = etat.devis;
                    $('w-devis').hidden = !d;
                    $('w-lignes').innerHTML = d
                        ? '<div><dt>Location</dt><dd>' + d.texte.location + '</dd></div>' +
                          '<div><dt>Commission VIVI (' + String(d.taux_commission).replace('.', ',') + ' %)<small>Non remboursable en cas d’annulation</small></dt><dd>' + d.texte.commission + '</dd></div>' +
                          '<div class="total"><dt>Total</dt><dd>' + d.texte.total + '</dd></div>'
                        : '';
                    $('w-continuer').disabled = !d;
                }

                function lire(params, signal) {
                    return fetch(form.dataset.url + '?' + new URLSearchParams(params).toString(), { headers: { Accept: 'application/json' }, signal: signal })
                        .then(function (r) { return r.json(); });
                }

                // Plus rien de libre aujourd'hui : premier des jours suivants où la formule est libre
                function prochainJourLibre(recu, params, signal, n) {
                    n = n || 1;
                    if (n > JOURS_CHERCHES) { return Promise.resolve(null); }
                    var jour = new Date(recu.date_min + 'T12:00:00'); jour.setDate(jour.getDate() + n);
                    var p = Object.assign({}, params, { date: jour.toISOString().slice(0, 10), formule: recu.formule });
                    return lire(p, signal).then(function (e) { return complet(e) ? prochainJourLibre(recu, params, signal, n + 1) : e; });
                }

                var enCours = null;
                function demander(modif) {
                    var p = { date: etat.date, formule: etat.formule || '', voyageurs: etat.voyageurs };
                    if (etat.heure) { p.heure = etat.heure; }
                    if (etat.duree) { p.duree = etat.duree; }
                    if (etat.creneau_id) { p.creneau_id = etat.creneau_id; }
                    if (etat.jours) { p.jours = etat.jours; }
                    // Date avancée par le site : une autre formule est peut-être encore libre aujourd'hui
                    if ('formule' in modif && etat.date === jourPropose) { p.date = etat.date_min; }
                    Object.assign(p, modif);
                    if (enCours) { enCours.abort(); }
                    enCours = new AbortController();
                    var signal = enCours.signal;
                    form.setAttribute('aria-busy', 'true');
                    $('w-continuer').disabled = true;
                    lire(p, signal)
                        .then(function (recu) {
                            if (recu.date === recu.date_min && complet(recu)) {
                                return prochainJourLibre(recu, p, signal).then(function (libre) {
                                    if (libre) { jourPropose = libre.date; return libre; }
                                    return recu;
                                });
                            }
                            if (recu.date !== jourPropose) { jourPropose = null; }
                            return recu;
                        })
                        .then(function (nouvel) { etat = nouvel; afficher(); })
                        .catch(function () {})
                        .finally(function () { form.removeAttribute('aria-busy'); $('w-continuer').disabled = !etat.devis; });
                }

                form.addEventListener('change', function (e) {
                    var t = e.target;
                    if (t.name === 'formule') { demander({ formule: t.value }); }
                    if (t.name === 'date' && t.value) { demander({ date: t.value }); }
                    if (t.name === 'heure') { demander({ heure: t.value }); }
                    if (t.name === 'creneau_id') { demander({ creneau_id: t.value }); }
                });
                form.addEventListener('click', function (e) {
                    var b = e.target.closest('.stepper [data-pas]');
                    if (!b) { return; }
                    var s = b.closest('.stepper'), v = +s.dataset.valeur + +b.dataset.pas;
                    if (v < +s.dataset.min || v > +s.dataset.max) { return; }
                    var modif = {}; modif[s.dataset.nom] = v;
                    if (s.dataset.nom === 'voyageurs') { etat.voyageurs = v; stepper('c-voyageurs', v, 1, etat.capacite); return; }
                    demander(modif);
                });
                // Le calendrier du téléphone s'ouvre d'un appui sur la date
                $('w-date').addEventListener('click', function () { try { this.showPicker(); } catch (e) {} });

                // « Continuer » : le créneau est retenu par le serveur, puis services et paiement.
                // Sans compte, on passe par la connexion et on revient sur cette fiche avec les mêmes choix.
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    if (!etat.devis) { return; }
                    var champs = { date: etat.date, formule: etat.formule, voyageurs: etat.voyageurs };
                    if (etat.formule === 'heure') { champs.heure = etat.heure; champs.duree = etat.duree; }
                    if (etat.formule === 'demi_journee') { champs.creneau_id = etat.creneau_id; }
                    if (etat.formule === 'journee') { champs.jours = etat.jours; }

                    if (form.dataset.connecte !== '1') {
                        var ici = window.location.pathname + '?' + new URLSearchParams(Object.assign({ reserver: 1 }, champs)).toString();
                        window.location.href = form.dataset.connexion + '?suite=' + encodeURIComponent(ici);
                        return;
                    }
                    var envoi = document.createElement('form');
                    envoi.method = 'POST';
                    envoi.action = form.dataset.reserver;
                    champs._token = document.querySelector('meta[name="csrf-token"]').content;
                    Object.keys(champs).forEach(function (k) {
                        var i = document.createElement('input');
                        i.type = 'hidden'; i.name = k; i.value = champs[k];
                        envoi.appendChild(i);
                    });
                    document.body.appendChild(envoi);
                    $('w-continuer').disabled = true;
                    envoi.submit();
                });

                // Retour de la connexion (ou d'une erreur de réservation) : la feuille se rouvre sur mobile
                var feuille = $('reserver');
                var rouvrir = new URLSearchParams(window.location.search).has('reserver') || {{ $errors->has('reservation') ? 'true' : 'false' }};
                // Bootstrap est chargé en module, après ce script : on attend la fin du chargement de la page
                document.addEventListener('DOMContentLoaded', function () {
                    if (rouvrir && window.matchMedia('(max-width: 991.98px)').matches && window.bootstrap) {
                        bootstrap.Offcanvas.getOrCreateInstance(feuille).show();
                    }
                });

                // Aujourd'hui déjà complet à l'ouverture : on cherche le prochain jour libre
                if (etat.formule && etat.date === etat.date_min && complet(etat)) { demander({}); }
                afficher();
            })();
        </script>
    @endpush
</x-layouts.public>
