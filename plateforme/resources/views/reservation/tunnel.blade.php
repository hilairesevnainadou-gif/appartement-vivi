{{-- Tunnel de réservation (maquette reservation.html) : étape « Services » puis « Paiement ».
     Le créneau est déjà retenu ; les montants sont recalculés par le serveur à chaque service ajouté. --}}
@php
    use App\Models\Reservation;
    use App\Support\Montant;
    use App\Support\Telephone;

    $r = $reservation;
    $l = $logement;
    $resteSecondes = (int) max(0, floor(now()->diffInSeconds($r->bloque_jusqua, false)));
    $erreurPaiement = $errors->has('paiement') || $errors->has('nom') || $errors->has('whatsapp') || $errors->has('cgv') || $errors->has('email');
@endphp

<x-layouts.public page="reservation" titre="Réserver · {{ $l->titre }}" :nav-basse="false" entete="tunnel" classe-corps="bg-sand">
    <div class="container py-4 py-lg-5">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <ol class="steps mb-0" aria-label="Étapes de la réservation">
                <li class="is-done"><span class="dot"><i class="bi bi-check-lg" aria-hidden="true"></i></span><span class="lbl">Créneau</span></li>
                <li class="{{ $erreurPaiement ? 'is-done' : 'is-current' }}" id="etape-services"><span class="dot">{!! $erreurPaiement ? '<i class="bi bi-check-lg" aria-hidden="true"></i>' : '2' !!}</span><span class="lbl">Services</span></li>
                <li class="{{ $erreurPaiement ? 'is-current' : '' }}" id="etape-paiement"><span class="dot">3</span><span class="lbl">Paiement</span></li>
            </ol>
            <span class="hold" id="retenue" data-secondes="{{ $resteSecondes }}">
                <i class="bi bi-hourglass-split" aria-hidden="true"></i>Créneau bloqué pour vous pendant <b id="compte-a-rebours" role="timer">{{ sprintf('%02d:%02d', intdiv($resteSecondes, 60), $resteSecondes % 60) }}</b>
            </span>
        </div>

        @error('paiement')
            <div class="note-box note-box--orange mt-3" role="alert"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>{{ $message }}</span></div>
        @enderror

        <form method="POST" action="{{ route('reservation.payer', $r->reference) }}" id="form-paiement" novalidate
              data-devis="{{ route('reservation.devis', $r->reference) }}">
            @csrf
            <div class="tunnel-grid mt-4">

                {{-- ===== Récapitulatif ===== --}}
                <aside class="tunnel-aside" aria-labelledby="rc-titre">
                    <div class="summary">
                        <div class="summary__head">
                            @if ($l->urlPhoto())<img src="{{ $l->urlPhoto() }}" alt="" width="640" height="480">@endif
                            <div>
                                <h2 id="rc-titre">{{ $l->titre }}</h2>
                                <p>{{ $l->quartier }}, {{ $l->ville }}</p>
                            </div>
                        </div>
                        <button class="btn btn-ghost btn-sm w-100 justify-content-between d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#rc-corps" aria-expanded="false" aria-controls="rc-corps">
                            <span>Total <strong class="text-ink tnum" data-total-court>{{ $devis['texte']['a_payer'] }}</strong></span>
                            <span>Voir le détail<i class="bi bi-chevron-down ms-1" aria-hidden="true"></i></span>
                        </button>
                        <div class="collapse d-lg-block" id="rc-corps">
                            <ul class="recap mt-3 mt-lg-0">
                                <li><i class="bi bi-calendar-event" aria-hidden="true"></i><span>{{ ucfirst($r->debut->translatedFormat('l j F Y')) }}</span></li>
                                <li><i class="bi bi-clock" aria-hidden="true"></i><span>{{ $r->creneauEnClair() }}</span></li>
                                @if ($r->personnes())<li><i class="bi bi-people" aria-hidden="true"></i><span>{{ $r->personnes() }}</span></li>@endif
                            </ul>
                            <dl class="price-lines" id="rc-lignes" aria-live="polite"></dl>
                            <p class="form-text mt-3 mb-0">La commission VIVI est à votre charge. Elle est comprise dans le total : aucun autre montant ne vous sera demandé.</p>
                        </div>
                    </div>
                </aside>

                <div class="tunnel-main">
                    {{-- ===== Étape 2 : services ===== --}}
                    <section id="vue-services" aria-labelledby="t-services" @if ($erreurPaiement) hidden @endif>
                        <div class="panel">
                            <h1 class="h3" id="t-services">Ajoutez vos services</h1>
                            @if ($l->services->isEmpty())
                                <p class="text-soft mb-0">{{ ucfirst($l->mot('ce')) }} ne propose pas de service à la carte. Passez directement au paiement.</p>
                            @else
                                <p class="text-soft mb-2">Facultatif. Le total se met à jour à chaque ajout.</p>
                                @foreach ($l->services as $s)
                                    @php $quantite = (int) old('services.'.$s->id, $choisis[$s->id] ?? 0); @endphp
                                    <div class="service-row{{ $quantite ? ' is-on' : '' }}" data-id="{{ $s->id }}">
                                        <span class="service-tile__icon"><i class="bi {{ $s->icone ?: 'bi-bag-check' }}" aria-hidden="true"></i></span>
                                        <div><h3 id="sv-{{ $s->id }}">{{ $s->nom }}</h3>@if ($s->description)<p>{{ $s->description }}</p>@endif</div>
                                        <div class="service-row__action">
                                            <span class="fw-bold text-ink tnum">{{ Montant::fcfa($s->prix) }} <span class="fw-normal text-soft small">{{ $s->unite }}</span></span>
                                            <div class="stepper" role="group" aria-labelledby="sv-{{ $s->id }}">
                                                <button type="button" data-pas="-1" aria-label="Retirer" @disabled($quantite <= 0)><i class="bi bi-dash-lg" aria-hidden="true"></i></button>
                                                <output aria-live="polite">{{ $quantite }}</output>
                                                <button type="button" data-pas="1" aria-label="Ajouter" @disabled($quantite >= 6)><i class="bi bi-plus-lg" aria-hidden="true"></i></button>
                                            </div>
                                            <input type="hidden" name="services[{{ $s->id }}]" value="{{ $quantite }}">
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                        <div class="btn-duo justify-content-between mt-3">
                            <a class="btn btn-ghost" href="{{ route('logement', ['slug' => $l->slug, 'date' => $r->debut->toDateString(), 'formule' => $r->formule->value]) }}">Modifier le créneau</a>
                            <button class="btn btn-cta btn-lg" id="vers-paiement" type="button">Continuer vers le paiement</button>
                        </div>
                    </section>

                    {{-- ===== Étape 3 : paiement ===== --}}
                    <section id="vue-paiement" aria-labelledby="t-paiement" @unless ($erreurPaiement) hidden @endunless>
                        <div class="panel">
                            <h1 class="h3" id="t-paiement">Vos coordonnées</h1>
                            <p class="text-soft">Votre QR Code et l’adresse {{ $l->mot('du') }} restent dans votre espace VIVI ; ils vous sont aussi envoyés par SMS (numéro béninois) et par email si vous l’indiquez.</p>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="p-nom">Nom et prénom <span aria-hidden="true">*</span></label>
                                    <input class="form-control @error('nom') is-invalid @enderror" type="text" id="p-nom" name="nom" autocomplete="name" required
                                           value="{{ old('nom', $utilisateur->name) }}">
                                    @error('nom')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="p-tel"><i class="bi bi-whatsapp me-1" aria-hidden="true"></i>Numéro WhatsApp <span aria-hidden="true">*</span></label>
                                    <input class="form-control @error('whatsapp') is-invalid @enderror" type="tel" id="p-tel" name="whatsapp" inputmode="tel" autocomplete="tel"
                                           placeholder="+229 01 97 00 00 00" required aria-describedby="p-tel-aide"
                                           value="{{ old('whatsapp', \App\Services\CoordonneesClient::suggestion($utilisateur)) }}">
                                    @error('whatsapp')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <p class="form-text mb-0" id="p-tel-aide">Avec l’indicatif de votre pays (ex. +33 6 12 34 56 78). Le gérant vous contacte sur WhatsApp si besoin.</p>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="p-email">Email <span class="text-soft fw-normal">(facultatif)</span></label>
                                    <input class="form-control @error('email') is-invalid @enderror" type="email" id="p-email" name="email" autocomplete="email" inputmode="email"
                                           placeholder="vous@exemple.com" value="{{ old('email', $utilisateur->email) }}" @if ($utilisateur->email) readonly @endif aria-describedby="p-email-aide">
                                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <p class="form-text mb-0" id="p-email-aide">{{ $utilisateur->email ? 'La confirmation, l’adresse '.$l->mot('du').' et le reçu vous sont aussi envoyés à cet email.' : 'Pour recevoir aussi par email la confirmation, l’adresse '.$l->mot('du').', l’itinéraire et le reçu.' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="panel">
                            <h2 id="t-moyen">Moyen de paiement</h2>
                            <p class="text-soft mb-3">Vous choisissez votre moyen de paiement sur la page sécurisée de FedaPay, notre prestataire de paiement.</p>
                            <div class="d-flex flex-wrap gap-2" aria-label="Moyens acceptés">
                                @foreach (config('vivi.paiement.moyens') as $moyen)
                                    <span class="chip chip--line"><i class="bi {{ $moyen === 'Carte bancaire' ? 'bi-credit-card' : 'bi-phone' }}" aria-hidden="true"></i>{{ $moyen }}</span>
                                @endforeach
                            </div>
                            <div class="note-box mt-3">
                                <i class="bi bi-info-circle" aria-hidden="true"></i>
                                <span>Avec Mobile Money, vous validez le paiement sur votre téléphone avec votre code secret. VIVI ne le voit jamais.</span>
                            </div>
                        </div>

                        @if ($bonusDisponible > 0)
                            <div class="panel bonus-choix">
                                <input type="hidden" name="utiliser_bonus" value="0">
                                <div class="form-check form-switch align-items-start m-0">
                                    <input class="form-check-input mt-1" type="checkbox" role="switch" id="p-bonus" name="utiliser_bonus" value="1" @checked($utiliserBonus)>
                                    <label class="form-check-label" for="p-bonus">
                                        <strong><i class="bi bi-gift me-1" aria-hidden="true"></i>Utiliser mon bonus</strong>
                                        <span class="d-block text-soft small">Vous avez {{ Montant::fcfa($bonusDisponible) }} de bonus. Il couvre jusqu’à {{ $plafondBonus }} % du total ; le reste se paie avec FedaPay.</span>
                                    </label>
                                </div>
                            </div>
                        @endif

                        <div class="panel">
                            <div class="form-check align-items-start">
                                <input class="form-check-input mt-1 @error('cgv') is-invalid @enderror" type="checkbox" id="p-cgv" name="cgv" value="1" required @checked(old('cgv'))>
                                <label class="form-check-label" for="p-cgv">J’accepte les <a href="{{ route('aide.conditions') }}" target="_blank">conditions générales</a> et les <a href="{{ route('aide.annulation') }}" target="_blank">conditions d’annulation et de report</a>.</label>
                                @error('cgv')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <button class="btn btn-cta btn-lg w-100 mt-3" type="submit" id="payer" data-chargement="Redirection vers le paiement sécurisé…"><i class="bi bi-lock-fill" aria-hidden="true"></i><span>Payer <span data-total-court>{{ $devis['texte']['a_payer'] }}</span></span></button>
                            <p class="form-text text-center mt-2 mb-0">Paiement traité par FedaPay. VIVI ne conserve aucune donnée bancaire.</p>
                        </div>
                        <button class="btn btn-ghost mt-3" id="vers-services" type="button">Retour aux services</button>
                    </section>

                    {{-- Délai dépassé --}}
                    <div class="note-box note-box--orange mt-3" id="expire" role="alert" hidden>
                        <i class="bi bi-hourglass-bottom" aria-hidden="true"></i>
                        <span>Le délai de réservation est écoulé. Vous pouvez encore payer si le créneau est toujours libre ; sinon, choisissez-en un autre.</span>
                    </div>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
        <script type="application/json" id="devis-initial">@json($devis)</script>
        <script>
            (function () {
                var $ = function (id) { return document.getElementById(id); };
                var form = $('form-paiement');
                var echapper = function (t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; };

                function recapituler(d) {
                    $('rc-lignes').innerHTML =
                        '<div><dt>' + echapper(d.detail_location) + '</dt><dd>' + d.texte.location + '</dd></div>' +
                        d.lignes_services.map(function (s) {
                            return '<div><dt>' + echapper(s.nom) + (s.quantite > 1 ? ' × ' + s.quantite : '') + '</dt><dd>' +
                                new Intl.NumberFormat('fr-FR').format(s.montant).replace(/\s/g, ' ') + ' FCFA</dd></div>';
                        }).join('') +
                        '<div><dt>Commission VIVI</dt><dd>' + d.texte.commission + '</dd></div>' +
                        (d.remise_agent > 0 ? '<div class="bonus"><dt>Remise agent (votre part marketing)</dt><dd>− ' + d.texte.remise_agent + '</dd></div>' : '') +
                        '<div class="total"><dt>Total</dt><dd>' + d.texte.total + '</dd></div>' +
                        (d.bonus > 0
                            ? '<div class="bonus"><dt>Payé avec votre bonus</dt><dd>− ' + d.texte.bonus + '</dd></div>' +
                              '<div class="total"><dt>À payer</dt><dd>' + d.texte.a_payer + '</dd></div>'
                            : '');
                    form.querySelectorAll('[data-total-court]').forEach(function (el) { el.textContent = d.texte.a_payer; });
                }

                // Services : compteurs − n +, prix recalculé par le serveur
                var enCours = null;
                function actualiser() {
                    var p = new URLSearchParams();
                    form.querySelectorAll('input[name^="services["]').forEach(function (i) { if (+i.value > 0) { p.set(i.name, i.value); } });
                    if ($('p-bonus') && $('p-bonus').checked) { p.set('bonus', '1'); }
                    if (enCours) { enCours.abort(); }
                    enCours = new AbortController();
                    var celleCi = enCours;
                    $('rc-lignes').classList.add('is-chargement'); $('rc-lignes').setAttribute('aria-busy', 'true');
                    fetch(form.dataset.devis + '?' + p.toString(), { headers: { Accept: 'application/json' }, signal: enCours.signal })
                        .then(function (r) { return r.json(); }).then(recapituler).catch(function () {})
                        .finally(function () { if (enCours === celleCi) { $('rc-lignes').classList.remove('is-chargement'); $('rc-lignes').removeAttribute('aria-busy'); } });
                }
                form.addEventListener('click', function (e) {
                    var b = e.target.closest('.service-row [data-pas]');
                    if (!b) { return; }
                    var ligne = b.closest('.service-row'), champ = ligne.querySelector('input[type=hidden]');
                    var n = Math.min(6, Math.max(0, +champ.value + +b.dataset.pas));
                    champ.value = n;
                    ligne.querySelector('output').textContent = n;
                    ligne.querySelector('[data-pas="-1"]').disabled = n <= 0;
                    ligne.querySelector('[data-pas="1"]').disabled = n >= 6;
                    ligne.classList.toggle('is-on', n > 0);
                    actualiser();
                });

                // Passage d'une étape à l'autre
                function etape(paiement) {
                    $('vue-services').hidden = paiement;
                    $('vue-paiement').hidden = !paiement;
                    $('etape-services').className = paiement ? 'is-done' : 'is-current';
                    $('etape-services').querySelector('.dot').innerHTML = paiement ? '<i class="bi bi-check-lg" aria-hidden="true"></i>' : '2';
                    $('etape-paiement').className = paiement ? 'is-current' : '';
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    (paiement ? $('p-nom') : $('t-services')).focus({ preventScroll: true });
                }
                $('vers-paiement').addEventListener('click', function () { etape(true); });
                $('vers-services').addEventListener('click', function () { etape(false); });

                form.addEventListener('submit', function () { $('payer').disabled = true; $('payer').setAttribute('aria-busy', 'true'); });

                // Compte à rebours de la retenue du créneau
                var reste = +$('retenue').dataset.secondes, sortie = $('compte-a-rebours');
                (function tic() {
                    if (reste <= 0) { sortie.textContent = '00:00'; $('expire').hidden = false; return; }
                    sortie.textContent = String(Math.floor(reste / 60)).padStart(2, '0') + ':' + String(reste % 60).padStart(2, '0');
                    reste--; setTimeout(tic, 1000);
                })();

                if ($('p-bonus')) { $('p-bonus').addEventListener('change', actualiser); }
                recapituler(JSON.parse($('devis-initial').textContent));
            })();
        </script>
    @endpush
</x-layouts.public>
