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
                                            De {{ substr($t->heure_ouverture, 0, 5) }} à {{ substr($t->heure_fermeture, 0, 5) }} · minimum {{ $t->duree_minimum_heures }} h
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
                    <aside class="booking-card" id="reserver" aria-labelledby="t-reserver">
                        <h2 class="visually-hidden" id="t-reserver">Réserver {{ $l->mot('ce') }}</h2>
                        <div class="booking-card__price">
                            <strong id="w-prix">{{ $formuleInitiale['prix_texte'] ?? '—' }}</strong><span id="w-unite">{{ $formuleInitiale['par'] ?? '' }}</span>
                        </div>

                        @error('reservation')
                            <div class="note-box note-box--orange mb-3" role="alert"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>{{ $message }}</span></div>
                        @enderror
                        <form id="form-reservation" novalidate data-url="{{ route('logement.disponibilites', $l->slug) }}"
                              data-reserver="{{ route('reservation.bloquer', $l->slug) }}" data-connexion="{{ route('connexion') }}" data-connecte="{{ auth()->check() ? '1' : '0' }}">
                            <div class="field">
                                <span class="form-label d-block" id="lbl-formule">Formule</span>
                                <div class="segmented" role="radiogroup" aria-labelledby="lbl-formule">
                                    @foreach (Formule::cases() as $f)
                                        <input type="radio" name="formule" id="w-{{ $f->value }}" value="{{ $f->value }}" @disabled(! $l->tarif($f)) @checked($etat['formule'] === $f->value)>
                                        <label for="w-{{ $f->value }}">{{ $f->court() }}</label>
                                    @endforeach
                                </div>
                            </div>

                            <div class="field">
                                <label class="form-label" for="w-date">Date</label>
                                <input class="form-control" type="date" id="w-date" name="date" min="{{ $etat['date_min'] }}" value="{{ $etat['date'] }}">
                            </div>

                            {{-- Formule « heure » --}}
                            <div class="field" data-formule="heure" hidden>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label" for="w-arrivee">{{ $l->estSalle() ? 'Heure de début' : 'Heure d’arrivée' }}</label>
                                        <select class="form-select" id="w-arrivee" name="heure"></select>
                                    </div>
                                    <div class="col-6">
                                        <span class="form-label d-block" id="lbl-duree">Durée</span>
                                        <div class="stepper" id="c-duree" role="group" aria-labelledby="lbl-duree" data-nom="duree" data-suffixe=" h">
                                            <button type="button" data-pas="-1" aria-label="Une heure de moins"><i class="bi bi-dash-lg" aria-hidden="true"></i></button>
                                            <output aria-live="polite"></output>
                                            <button type="button" data-pas="1" aria-label="Une heure de plus"><i class="bi bi-plus-lg" aria-hidden="true"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Formule « demi-journée » --}}
                            <fieldset class="field" data-formule="demi_journee" hidden>
                                <legend class="form-label">Créneau</legend>
                                <div class="slot-grid" id="w-creneaux"></div>
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

                            {{-- Nombre de voyageurs : logements seulement (une salle se réserve entière, sa capacité est affichée) --}}
                            @unless ($l->estSalle())
                                <div class="field field-row mt-3">
                                    <span class="form-label" id="lbl-voyageurs">Voyageurs</span>
                                    <div class="stepper" id="c-voyageurs" role="group" aria-labelledby="lbl-voyageurs" data-nom="voyageurs" data-suffixe="">
                                        <button type="button" data-pas="-1" aria-label="Un voyageur de moins"><i class="bi bi-dash-lg" aria-hidden="true"></i></button>
                                        <output aria-live="polite"></output>
                                        <button type="button" data-pas="1" aria-label="Un voyageur de plus"><i class="bi bi-plus-lg" aria-hidden="true"></i></button>
                                    </div>
                                </div>
                            @endunless

                            <div class="note-box note-box--orange" id="w-message" role="status" hidden><i class="bi bi-calendar-x" aria-hidden="true"></i><span></span></div>
                            <dl class="price-lines" id="w-lignes" aria-live="polite"></dl>
                            <p class="form-text mt-2 mb-0">Services à la carte à choisir à l’étape suivante.</p>

                            <button class="btn btn-cta btn-lg w-100 mt-3" type="submit" id="w-continuer">Continuer<i class="bi bi-arrow-right" aria-hidden="true"></i></button>
                        </form>

                        <ul class="reassure">
                            <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>Aucun paiement à cette étape.</span></li>
                            <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>Entrée par QR Code à votre arrivée.</span></li>
                            <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>Report gratuit jusqu’à {{ $delaiReport }} h avant l’arrivée.</span></li>
                        </ul>
                    </aside>
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
            <a class="btn btn-cta" href="#reserver">Réserver</a>
        </div>
    @endpush

    @push('scripts')
        <script type="application/json" id="etat-initial">@json($etat)</script>
        <script>
            /* Bloc « Réserver » : l'état (disponibilités et prix) vient toujours du serveur */
            (function () {
                var form = document.getElementById('form-reservation');
                var $ = function (id) { return document.getElementById(id); };
                var etat = JSON.parse($('etat-initial').textContent);
                var echapper = function (t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; };

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

                    var f = (etat.formules || []).filter(function (x) { return x.valeur === formule; })[0];
                    if (f) {
                        $('w-prix').textContent = f.prix_texte; $('w-unite').textContent = f.par;
                        $('bb-prix').textContent = f.prix_texte; $('bb-unite').textContent = f.unite;
                    }

                    // Heure : seules les heures libres sont proposées
                    var select = $('w-arrivee');
                    select.innerHTML = etat.heures.map(function (h) {
                        return '<option value="' + h.heure + '"' + (h.libre ? '' : ' disabled') + (h.heure === etat.heure ? ' selected' : '') + '>' +
                            h.heure + (h.libre ? '' : ' · pris') + '</option>';
                    }).join('');
                    if (etat.duree) { stepper('c-duree', etat.duree, etat.duree_min, etat.duree_max, function (n) { return n + ' h'; }); }

                    // Demi-journée : créneaux, complets désactivés
                    $('w-creneaux').innerHTML = etat.creneaux.map(function (c) {
                        return '<div class="slot"><input type="radio" name="creneau_id" id="cr-' + c.id + '" value="' + c.id + '"' +
                            (c.libre ? '' : ' disabled') + (c.id === etat.creneau_id ? ' checked' : '') + '>' +
                            '<label for="cr-' + c.id + '"><strong>' + echapper(c.nom) + '</strong>' +
                            '<span>' + (c.libre ? c.de + ' – ' + c.a : 'Complet ce jour-là') + '</span></label></div>';
                    }).join('');

                    if (etat.jours) { stepper('c-jours', etat.jours, 1, etat.jours_max); }
                    if ($('c-voyageurs')) { stepper('c-voyageurs', etat.voyageurs, 1, etat.capacite); }   // absent pour une salle

                    $('w-aide').textContent = etat.aide || '';
                    $('w-aide').hidden = !etat.aide;
                    var message = $('w-message');
                    message.hidden = !etat.message;
                    message.querySelector('span').textContent = etat.message || '';

                    var d = etat.devis;
                    $('w-lignes').innerHTML = d
                        ? '<div><dt>' + echapper(d.detail_location) + '</dt><dd>' + d.texte.location + '</dd></div>' +
                          '<div><dt>Commission VIVI</dt><dd>' + d.texte.commission + '</dd></div>' +
                          '<div class="total"><dt>Total</dt><dd>' + d.texte.total + '</dd></div>'
                        : '';
                    $('w-continuer').disabled = !d;
                }

                var enCours = null;
                function demander(modif) {
                    var p = new URLSearchParams({ date: etat.date, formule: etat.formule || '', voyageurs: etat.voyageurs });
                    if (etat.heure) { p.set('heure', etat.heure); }
                    if (etat.duree) { p.set('duree', etat.duree); }
                    if (etat.creneau_id) { p.set('creneau_id', etat.creneau_id); }
                    if (etat.jours) { p.set('jours', etat.jours); }
                    Object.keys(modif).forEach(function (k) { p.set(k, modif[k]); });
                    if (enCours) { enCours.abort(); }
                    enCours = new AbortController();
                    form.setAttribute('aria-busy', 'true');
                    fetch(form.dataset.url + '?' + p.toString(), { headers: { Accept: 'application/json' }, signal: enCours.signal })
                        .then(function (r) { return r.json(); })
                        .then(function (nouvel) { etat = nouvel; afficher(); })
                        .catch(function () {})
                        .finally(function () { form.removeAttribute('aria-busy'); });
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
                        var ici = window.location.pathname + '?' + new URLSearchParams(champs).toString();
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

                // La barre basse disparaît quand le bloc de réservation est à l'écran
                var barre = $('barre-reserver');
                if ('IntersectionObserver' in window && barre) {
                    new IntersectionObserver(function (entrees) { barre.classList.toggle('d-none', entrees[0].isIntersecting); }, { threshold: 0.15 })
                        .observe($('reserver'));
                }

                afficher();
            })();
        </script>
    @endpush
</x-layouts.public>
