/* Tunnel de réservation : services à la carte puis paiement */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var D = window.VIVI_DATA, V = window.VIVI;
  var $ = function (id) { return document.getElementById(id); };

  var r = V.lireReservation() || V.reservationParDefaut();
  var l = V.logement(r.logement);
  if (!r.debutBlocage) { r.debutBlocage = Date.now(); }
  V.ecrireReservation(r);

  /* ---------- Récapitulatif ---------- */
  $('rc-photo').src = V.photo(l.photos[0], 640);
  $('rc-titre').textContent = l.titre;
  $('rc-lieu').textContent = l.quartier + ', ' + l.ville;
  $('rc-date').textContent = V.dateLongue(r.date);
  $('rc-voyageurs').textContent = V.pluriel(r.voyageurs, 'voyageur');
  $('modifier').href = 'logement.html?id=' + encodeURIComponent(l.id);

  function recapituler() {
    var k = V.calculer(r);
    $('rc-creneau').textContent = k.creneau;
    $('rc-lignes').innerHTML =
      '<div><dt>' + V.esc(k.detail) + '</dt><dd>' + V.fcfa(k.location) + '</dd></div>' +
      k.lignesServices.map(function (s) {
        return '<div><dt>' + V.esc(s.nom) + (s.quantite > 1 ? ' × ' + s.quantite : '') + '</dt><dd>' + V.fcfa(s.montant) + '</dd></div>';
      }).join('') +
      '<div><dt>Commission VIVI</dt><dd>' + V.fcfa(k.frais) + '</dd></div>' +
      '<div class="total"><dt>Total</dt><dd>' + V.fcfa(k.total) + '</dd></div>';
    $('rc-total-court').textContent = V.fcfa(k.total);
    $('payer-texte').textContent = 'Payer ' + V.fcfa(k.total);
    return k;
  }

  /* ---------- Étape 2 : services activés par le propriétaire pour ce logement ---------- */
  $('liste-services').innerHTML = l.services.map(function (id) {
    var s = V.service(id);
    return '<div class="service-row" data-id="' + id + '">' +
      '<span class="service-tile__icon"><i class="bi ' + s.icone + '" aria-hidden="true"></i></span>' +
      '<div><h3 id="sv-' + id + '">' + V.esc(s.nom) + '</h3><p>' + V.esc(s.desc) + '</p></div>' +
      '<div class="service-row__action">' +
        '<span class="fw-bold text-ink tnum">' + V.fcfa(s.prix) + ' <span class="fw-normal text-soft small">' + V.esc(s.unite) + '</span></span>' +
        '<div class="stepper" role="group" aria-labelledby="sv-' + id + '">' +
          '<button type="button" data-pas="-1" aria-label="Retirer"><i class="bi bi-dash-lg" aria-hidden="true"></i></button>' +
          '<output aria-live="polite">0</output>' +
          '<button type="button" data-pas="1" aria-label="Ajouter"><i class="bi bi-plus-lg" aria-hidden="true"></i></button>' +
        '</div>' +
      '</div></div>';
  }).join('');

  [].forEach.call(document.querySelectorAll('.service-row'), function (ligne) {
    var id = ligne.dataset.id;
    var marquer = function (n) { ligne.classList.toggle('is-on', n > 0); };
    V.compteur(ligne.querySelector('.stepper'), {
      valeur: r.services[id] || 0, min: 0, max: 6,
      auChangement: function (n) {
        if (n > 0) { r.services[id] = n; } else { delete r.services[id]; }
        marquer(n); V.ecrireReservation(r); recapituler();
      }
    });
    marquer(r.services[id] || 0);
  });

  /* ---------- Passage d'une étape à l'autre ---------- */
  var vues = { services: $('vue-services'), paiement: $('vue-paiement'), attente: $('vue-attente') };
  function montrer(nom) {
    Object.keys(vues).forEach(function (k) { vues[k].hidden = k !== nom; });
    var paiement = nom !== 'services';
    $('etape-services').className = paiement ? 'is-done' : 'is-current';
    $('etape-services').querySelector('.dot').innerHTML = paiement ? '<i class="bi bi-check-lg" aria-hidden="true"></i>' : '2';
    $('etape-paiement').className = paiement ? 'is-current' : '';
    window.scrollTo(0, 0);
    var titre = vues[nom].querySelector('h1');
    if (titre) { titre.setAttribute('tabindex', '-1'); titre.focus({ preventScroll: true }); }
  }
  $('vers-paiement').addEventListener('click', function () { montrer('paiement'); });
  $('vers-services').addEventListener('click', function () { montrer('services'); });

  /* ---------- Étape 3 : paiement ---------- */
  var form = $('form-paiement'), nom = $('p-nom'), tel = $('p-tel'), cgv = $('p-cgv');
  function moyen() { return form.querySelector('[name="moyen"]:checked').value; }
  function aideMoyen() {
    $('aide-moyen').textContent = moyen() === 'Carte bancaire'
      ? 'Vous serez dirigé vers la page de paiement sécurisée pour saisir votre carte.'
      : 'Vous recevrez une demande de confirmation ' + moyen() + ' sur votre téléphone. Validez-la avec votre code secret.';
  }
  form.addEventListener('change', aideMoyen);
  aideMoyen();

  function chiffres(s) { return s.replace(/\D/g, ''); }
  function verifier(champ, ok) { champ.classList.toggle('is-invalid', !ok); return ok; }
  nom.addEventListener('blur', function () { if (nom.value) { verifier(nom, nom.value.trim().length >= 3); } });
  tel.addEventListener('blur', function () { if (tel.value) { verifier(tel, chiffres(tel.value).length === 10); } });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var okNom = verifier(nom, nom.value.trim().length >= 3);
    var okTel = verifier(tel, chiffres(tel.value).length === 10);
    var okCgv = cgv.checked;
    cgv.classList.toggle('is-invalid', !okCgv);
    $('p-cgv-erreur').style.display = okCgv ? '' : 'block';
    if (!okNom) { nom.focus(); return; }
    if (!okTel) { tel.focus(); return; }
    if (!okCgv) { cgv.focus(); return; }

    var k = recapituler(), carte = moyen() === 'Carte bancaire';
    $('attente-titre').textContent = carte ? 'Paiement en cours' : 'Confirmez le paiement sur votre téléphone';
    $('attente-texte').textContent = carte
      ? 'Nous attendons la réponse de votre banque pour ' + V.fcfa(k.total) + '.'
      : 'Une demande ' + moyen() + ' de ' + V.fcfa(k.total) + ' vient d’être envoyée au +229 ' + tel.value.trim() + '.';
    montrer('attente');

    /* En production : la passerelle confirme le paiement au serveur, qui crée la réservation et le QR Code. */
    var lettres = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789', ref = '';
    for (var i = 0; i < 6; i++) { ref += lettres.charAt(Math.floor(Math.random() * lettres.length)); }
    r.reference = 'VIVI-' + ref;
    r.code = String(Math.floor(100000 + Math.random() * 900000));
    r.moyen = moyen();
    r.nom = nom.value.trim();
    r.telephone = tel.value.trim();
    V.ecrireReservation(r);
    setTimeout(function () { window.location.href = 'confirmation.html'; }, 2600);
  });

  /* ---------- Créneau bloqué le temps du paiement ---------- */
  var fin = r.debutBlocage + D.dureeBlocageMinutes * 60000, prevenu = false;
  function tic() {
    var reste = Math.max(0, Math.round((fin - Date.now()) / 1000));
    var m = Math.floor(reste / 60), s = reste % 60;
    $('compte-a-rebours').textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    if (reste === 0 && !prevenu) {
      prevenu = true;
      V.message('Le créneau n’est plus bloqué. Vérifiez qu’il est toujours disponible avant de payer.');
    }
  }
  tic();
  setInterval(tic, 1000);

  recapituler();
});
