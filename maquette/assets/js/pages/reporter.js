/* Espace client : report d'une réservation. Possible jusqu'à 3 h avant l'arrivée, vers une date dans les 10 jours. */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var V = window.VIVI;
  var $ = function (id) { return document.getElementById(id); };

  var r = V.reservationClient(V.qs('ref'));
  var k = V.calculer(r), l = k.logement, c = l.creneaux;
  var lienDetail = 'ma-reservation.html?ref=' + encodeURIComponent(r.reference);
  var limite = V.limiteReport(r);
  var choix = { date: null, heureDebut: r.heureDebut, demi: r.demi };

  $('retour').href = lienDetail;
  $('rp-resume').textContent = l.titre + ' · prévu le ' + V.dateLongue(r.date) + ' · ' + k.creneau.charAt(0).toLowerCase() + k.creneau.slice(1);
  $('rp-limite').textContent = V.dateLongue(limite);

  /* Dates proposées : de demain jusqu'à la date limite, sauf la date actuelle */
  var jours = [];
  for (var d = V.ajouterJours(V.aujourdhui(), 1); d <= limite; d = V.ajouterJours(d, 1)) {
    if (d !== r.date) { jours.push(d); }
  }
  if (!jours.length || !V.reportPossible(r)) {
    $('vue-choix').hidden = true;
    $('vue-trop-tard').hidden = false;
    return;
  }

  $('rp-jours').innerHTML = jours.map(function (iso, i) {
    var complet = (+iso.slice(8) % 4 === 0);   /* disponibilités inventées pour la maquette */
    var p = new Intl.DateTimeFormat('fr-FR', { weekday: 'short' }).format(new Date(iso + 'T12:00:00'));
    var j = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short' }).format(new Date(iso + 'T12:00:00'));
    return '<div class="slot"><input type="radio" name="jour" id="j-' + i + '" value="' + iso + '"' + (complet ? ' disabled' : '') + '>' +
      '<label for="j-' + i + '"><strong>' + p + '</strong><span>' + (complet ? 'Complet' : j) + '</span></label></div>';
  }).join('');

  /* Créneau : même formule et même durée que la réservation d'origine */
  var zone = $('rp-creneau');
  if (r.formule === 'heure') {
    var options = '';
    for (var h = c.heure.debut; h <= c.heure.fin - r.duree; h++) {
      options += '<option value="' + h + '"' + (h === r.heureDebut ? ' selected' : '') + '>' + V.heure(h) + '</option>';
    }
    zone.innerHTML = '<label class="form-label" for="rp-debut">Heure d’arrivée</label>' +
      '<select class="form-select" id="rp-debut" style="max-width:220px">' + options + '</select>' +
      '<p class="form-text mb-0 mt-2">Durée inchangée : ' + r.duree + ' h.</p>';
  } else if (r.formule === 'demi') {
    zone.innerHTML = '<div class="slot-grid" role="radiogroup" aria-labelledby="t-creneau">' + c.demi.map(function (x) {
      return '<div class="slot"><input type="radio" name="demi" id="rp-' + x.id + '" value="' + x.id + '"' + (x.id === r.demi ? ' checked' : '') + '>' +
        '<label for="rp-' + x.id + '"><strong><i class="bi ' + x.icone + '" aria-hidden="true"></i>' + V.esc(x.label) + '</strong><span>' + x.de + ' – ' + x.a + '</span></label></div>';
    }).join('') + '</div>';
  } else {
    zone.innerHTML = '<p class="mb-0">Arrivée à partir de ' + c.jour.arrivee + ', départ avant ' + c.jour.depart + '. Durée inchangée : ' + V.pluriel(r.jours, 'jour') + '.</p>';
  }

  function apercu() {
    var futur = Object.assign({}, r, { date: choix.date || r.date, heureDebut: choix.heureDebut, demi: choix.demi });
    $('rp-r-date').textContent = choix.date ? V.dateLongue(choix.date) : 'À choisir';
    $('rp-r-creneau').textContent = V.calculer(futur).creneau;
  }

  var form = $('form-report');
  form.addEventListener('change', function (e) {
    var t = e.target;
    if (t.name === 'jour') { choix.date = t.value; $('rp-jour-erreur').style.display = ''; }
    if (t.name === 'demi') { choix.demi = t.value; }
    if (t.id === 'rp-debut') { choix.heureDebut = +t.value; }
    if (t.id === 'rp-accord') { $('rp-accord-erreur').style.display = ''; }
    apercu();
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var accord = $('rp-accord');
    $('rp-jour-erreur').style.display = choix.date ? '' : 'block';
    $('rp-accord-erreur').style.display = accord.checked ? '' : 'block';
    if (!choix.date) { var premier = form.querySelector('[name="jour"]:not(:disabled)'); if (premier) { premier.focus(); } return; }
    if (!accord.checked) { accord.focus(); return; }

    V.modifierReservation(r.reference, {
      dateInitiale: r.dateInitiale || r.date,
      date: choix.date, heureDebut: choix.heureDebut, demi: choix.demi,
      statut: 'reportee',
      code: String(Math.floor(100000 + Math.random() * 900000))   /* nouveau QR Code */
    });
    $('rp-f-date').textContent = V.dateLongue(choix.date);
    $('rp-f-lien').href = lienDetail;
    $('vue-choix').hidden = true;
    $('vue-fait').hidden = false;
    window.scrollTo(0, 0);
  });

  apercu();
});
