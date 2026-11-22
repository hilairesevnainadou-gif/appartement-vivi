{{-- Espace propriétaire : scanner le QR Code à l'arrivée du client (maquette pro-scanner.html) --}}
<x-layouts.pro titre="Scanner un QR Code" page="scanner">
    @push('head')
        @vite('resources/js/scanner.js')
        <style>
            /* Vidéo de la caméra dans le cadre de visée */
            .scan-frame video { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
            .scan-frame.is-actif > :not(video):not(.coin):not(.ligne) { visibility: hidden; }
            .scan-frame.is-actif .ligne { z-index: 1; }
            .scan-frame .arreter { position: absolute; bottom: 16px; left: 50%; transform: translateX(-50%); z-index: 2; visibility: visible !important; }
        </style>
    @endpush

    <div class="pro-head">
        <div>
            <h1>Scanner un QR Code</h1>
            <p>À l’arrivée du client, scannez son QR Code : le séjour démarre et le montant de la location s’ajoute à votre solde.</p>
        </div>
    </div>

    <div class="row g-3" data-verifier="{{ route('pro.scanner.verifier') }}" data-demarrer="{{ route('pro.scanner.demarrer') }}" id="scanner">
        {{-- ===== Scan ou saisie du code ===== --}}
        <div class="col-lg-5">
            <section class="panel" aria-labelledby="t-scan">
                <h2 id="t-scan" class="visually-hidden">Scanner ou saisir le code</h2>
                <div class="scan-frame" id="cadre">
                    <span class="coin" aria-hidden="true"></span>
                    <span class="ligne" aria-hidden="true"></span>
                    <i class="bi bi-qr-code-scan" aria-hidden="true"></i>
                    <p class="mb-1">Placez le QR Code du client dans le cadre.</p>
                    <button class="btn btn-light-on-dark btn-sm" type="button" id="sc-camera"><i class="bi bi-camera" aria-hidden="true"></i>Activer la caméra</button>
                </div>
                <div class="ou">ou</div>
                <form id="form-code" novalidate>
                    <label class="form-label" for="sc-code">Code de secours du client</label>
                    <input class="form-control otp-input" type="text" id="sc-code" inputmode="numeric" autocomplete="off" maxlength="6" aria-describedby="sc-code-aide sc-code-erreur">
                    <div class="invalid-feedback" id="sc-code-erreur">Le code contient 6 chiffres.</div>
                    <p class="form-text mb-0 mt-2" id="sc-code-aide">Les 6 chiffres affichés sous le QR Code du client.</p>
                    <button class="btn btn-primary w-100 mt-3" type="submit">Vérifier le code</button>
                </form>
            </section>
        </div>

        {{-- ===== Résultat ===== --}}
        <div class="col-lg-7">
            <div id="sc-resultat" aria-live="polite"></div>
            <section class="panel" aria-labelledby="t-attendus">
                <div class="panel__head"><h2 id="t-attendus">Clients attendus aujourd’hui</h2></div>
                <ul class="rows">
                    @forelse ($attendus as $r)
                        <li>
                            <div class="quand">{{ $r->debut->format('H:i') }}<small>{{ $r->dureeCourte() }}</small></div>
                            <div class="qui">
                                <strong>{{ $r->client->name }}{{ $r->suffixePersonnes() }}</strong>
                                <span>{{ $r->logement->titre }} · {{ $r->reference }}</span>
                                @include('pro.partials.whatsapp-client', ['r' => $r])
                            </div>
                        </li>
                    @empty
                        <li style="display:block" class="text-soft">Plus aucun client attendu aujourd’hui.</li>
                    @endforelse
                </ul>
                <p class="form-text mt-3 mb-0">Le code de secours n’apparaît pas ici : seul le client le connaît. Demandez-le-lui si son QR Code ne passe pas.</p>
            </section>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                var $ = function (id) { return document.getElementById(id); };
                var zone = $('scanner'), resultat = $('sc-resultat'), code = $('sc-code'), cadre = $('cadre');
                var jeton = document.querySelector('meta[name="csrf-token"]').content;
                var echapper = function (t) { var d = document.createElement('div'); d.textContent = t == null ? '' : t; return d.innerHTML; };
                var arreterCamera = null;

                function envoyer(url, donnees) {
                    return fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': jeton },
                        body: JSON.stringify(donnees),
                    }).then(function (r) {
                        if (r.status === 429) { return { valide: false, titre: 'Trop d’essais', texte: 'Patientez une minute avant de vérifier un autre code.' }; }
                        return r.json().catch(function () { return { valide: false, titre: 'Erreur', texte: 'Réessayez dans un instant.' }; });
                    });
                }

                function erreur(titre, texte) {
                    resultat.innerHTML = '<section class="scan-result scan-result--erreur mb-3" role="alert">' +
                        '<h2 class="h5"><i class="bi bi-exclamation-octagon me-2" style="color:var(--vivi-danger)" aria-hidden="true"></i>' + echapper(titre) + '</h2>' +
                        '<p class="mb-0">' + echapper(texte) + '</p></section>';
                }

                function verifier(saisie) {
                    resultat.innerHTML = '<p class="text-soft"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Vérification…</p>';
                    envoyer(zone.dataset.verifier, { saisie: saisie }).then(function (rep) {
                        if (!rep.valide) { erreur(rep.titre, rep.texte); return; }
                        var r = rep.reservation;
                        resultat.innerHTML = '<section class="scan-result mb-3" aria-labelledby="t-trouve">' +
                            '<h2 class="h5" id="t-trouve"><i class="bi bi-check-circle-fill me-2" style="color:var(--vivi-success)" aria-hidden="true"></i>Réservation valide</h2>' +
                            '<dl class="detail-list mt-3">' +
                            '<div><dt>Client</dt><dd>' + echapper(r.client) + '</dd></div>' +
                            '<div><dt>' + echapper(r.mot || 'Logement') + '</dt><dd>' + echapper(r.logement) + '</dd></div>' +
                            '<div><dt>Créneau</dt><dd>' + echapper(r.creneau) + '</dd></div>' +
                            '<div><dt>À préparer</dt><dd>' + echapper(r.services) + '</dd></div>' +
                            '<div><dt>Référence</dt><dd class="tnum">' + echapper(r.reference) + '</dd></div></dl>' +
                            '<button class="btn btn-cta btn-lg w-100 mt-3" type="button" id="sc-demarrer" data-reference="' + echapper(r.reference) + '"><i class="bi bi-door-open" aria-hidden="true"></i>Démarrer le séjour</button>' +
                            '<p class="form-text text-center mb-0 mt-2">Un QR Code ne sert qu’une fois : le séjour démarre dès que vous validez.</p></section>';
                        resultat.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    });
                }

                resultat.addEventListener('click', function (e) {
                    var b = e.target.closest('#sc-demarrer');
                    if (b) {
                        window.viviConfirmer({
                            titre: 'Démarrer le séjour ?',
                            message: 'Le QR Code de la réservation ' + b.dataset.reference + ' ne servira plus : vérifiez que le client est bien devant vous.',
                            bouton: 'Démarrer le séjour',
                        }).then(function (ok) { if (ok) { demarrer(b); } });
                    }
                });

                // Démarrage du séjour après confirmation : bouton en chargement pendant l'appel
                function demarrer(b) {
                        window.viviChargement.demarrer(b);
                        envoyer(zone.dataset.demarrer, { reference: b.dataset.reference }).then(function (rep) {
                            if (rep.valide === false || !rep.heure) { window.viviChargement.arreter(b); erreur(rep.titre || 'Vérifiez de nouveau', rep.texte || rep.message || 'Saisissez à nouveau le code du client.'); return; }
                            resultat.innerHTML = '<section class="panel done mb-3" role="status">' +
                                '<div class="check-badge"><i class="bi bi-check-lg" aria-hidden="true"></i></div>' +
                                '<h2 class="h3">Séjour démarré à ' + echapper(rep.heure) + '</h2>' +
                                '<p class="mb-1">' + echapper(rep.client) + ' · ' + echapper(rep.logement) + '</p>' +
                                (rep.credit
                                    ? '<p class="text-soft"><strong class="text-ink">' + echapper(rep.credit) + '</strong> s’ajoutent à votre solde.</p>'
                                    : '<p class="text-soft">Réservation hors plateforme : vous avez encaissé vous-même, rien n’est ajouté au solde.</p>') +
                                '<div class="d-flex flex-wrap justify-content-center gap-2">' +
                                '<a class="btn btn-primary" href="' + window.location.pathname + '"><i class="bi bi-qr-code-scan" aria-hidden="true"></i>Scanner un autre QR Code</a>' +
                                '<a class="btn btn-ghost" href="{{ route('espace.proprietaire') }}">Tableau de bord</a></div></section>';
                        });
                }

                code.addEventListener('input', function () { code.value = code.value.replace(/\D/g, '').slice(0, 6); code.classList.remove('is-invalid'); });
                $('form-code').addEventListener('submit', function (e) {
                    e.preventDefault();
                    if (!/^\d{6}$/.test(code.value)) { code.classList.add('is-invalid'); code.focus(); return; }
                    verifier(code.value);
                });

                // Caméra : le QR Code lu est vérifié comme un code saisi
                $('sc-camera').addEventListener('click', function () {
                    if (!navigator.mediaDevices || !window.viviScanner) {
                        erreur('Caméra indisponible', 'Ce navigateur n’autorise pas la caméra sur cette page. Saisissez le code de secours du client.');
                        return;
                    }
                    var video = document.createElement('video');
                    var stop = document.createElement('button');
                    stop.type = 'button'; stop.className = 'btn btn-light-on-dark btn-sm arreter'; stop.textContent = 'Arrêter la caméra';
                    cadre.prepend(video); cadre.appendChild(stop); cadre.classList.add('is-actif');
                    function fermer() { if (arreterCamera) { arreterCamera(); } video.remove(); stop.remove(); cadre.classList.remove('is-actif'); }
                    stop.addEventListener('click', fermer);
                    window.viviScanner.demarrerLecture(video, function (texte) { fermer(); verifier(texte); })
                        .then(function (arreter) { arreterCamera = arreter; })
                        .catch(function () { fermer(); erreur('Caméra refusée', 'Autorisez la caméra dans votre navigateur, ou saisissez le code de secours du client.'); });
                });
            })();
        </script>
    @endpush
</x-layouts.pro>
