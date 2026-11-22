/* Espace client : liste des réservations */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var V = window.VIVI;
  var $ = function (id) { return document.getElementById(id); };

  var toutes = V.reservationsClient();
  var groupes = {
    avenir: ['confirmee', 'reportee'],
    passees: ['terminee'],
    annulees: ['annulee']
  };
  var vides = {
    avenir: ['Aucune réservation à venir', 'Trouvez un logement pour une heure, une demi-journée ou la journée.'],
    passees: ['Aucun séjour passé', 'Vos séjours terminés apparaîtront ici.'],
    annulees: ['Aucune réservation annulée', 'Les réservations que vous annulez apparaîtront ici.']
  };

  function etoiles(n) {
    var h = '';
    for (var i = 1; i <= 5; i++) { h += '<i class="bi ' + (i <= n ? 'bi-star-fill' : 'bi-star') + '" aria-hidden="true"></i>'; }
    return '<span class="stars" aria-label="Votre note : ' + n + ' sur 5">' + h + '</span>';
  }

  function carte(r) {
    var k = V.calculer(r), l = k.logement, ref = encodeURIComponent(r.reference);
    var note = '', actions = '';

    if (r.statut === 'confirmee' || r.statut === 'reportee') {
      if (r.dateInitiale) {
        note = '<div class="note-box"><i class="bi bi-arrow-repeat" aria-hidden="true"></i><span>Reportée : la date prévue au départ était le ' + V.dateCourte(r.dateInitiale) + '.</span></div>';
      }
      actions = '<a class="btn btn-primary" href="ma-reservation.html?ref=' + ref + '"><i class="bi bi-qr-code" aria-hidden="true"></i>QR Code</a>' +
        (V.reportPossible(r) ? '<a class="btn btn-ghost" href="reporter.html?ref=' + ref + '">Reporter</a>' : '');
      if (!V.reportPossible(r)) {
        note += '<p class="form-text mb-0 mt-2"><i class="bi bi-lock me-1" aria-hidden="true"></i>Report impossible à moins de 3 h de l’arrivée. Seuls le propriétaire ou VIVI peuvent encore déplacer cette réservation.</p>';
      }
    } else if (r.statut === 'terminee') {
      actions = (r.noteDonnee ? '' : '<a class="btn btn-primary" href="ma-reservation.html?ref=' + ref + '#avis"><i class="bi bi-star" aria-hidden="true"></i>Donner mon avis</a>') +
        '<a class="btn btn-ghost" href="logement.html?id=' + encodeURIComponent(l.id) + '">Réserver à nouveau</a>';
      if (r.noteDonnee) { note = '<p class="mb-0 mt-2 small">' + etoiles(r.noteDonnee) + ' <span class="text-soft">Votre avis est publié.</span></p>'; }
    } else {
      var rendu = typeof r.rembourse === 'number' ? r.rembourse : k.total;
      note = '<div class="note-box"><i class="bi bi-cash-coin" aria-hidden="true"></i><span>' +
        (rendu > 0 ? 'Remboursement de <strong>' + V.fcfa(rendu) + '</strong> sur votre compte ' + V.esc(r.moyen) + '.' : 'Annulation sans remboursement.') + '</span></div>';
      actions = '<a class="btn btn-ghost" href="logement.html?id=' + encodeURIComponent(l.id) + '">Réserver à nouveau</a>';
    }

    return '<article class="resa-card">' +
      '<img class="resa-card__photo" src="' + V.photo(l.photos[0], 640) + '" alt="" width="640" height="480" loading="lazy">' +
      '<div>' +
        '<div class="d-flex flex-wrap align-items-center gap-2">' + V.pastille(r.statut) + '<span class="small text-soft tnum">' + V.esc(r.reference) + '</span></div>' +
        '<h2><a href="ma-reservation.html?ref=' + ref + '">' + V.esc(l.titre) + '</a></h2>' +
        '<ul class="recap">' +
          '<li><i class="bi bi-calendar-event" aria-hidden="true"></i><span>' + V.dateLongue(r.date) + '</span></li>' +
          '<li><i class="bi bi-clock" aria-hidden="true"></i><span>' + V.esc(k.creneau) + '</span></li>' +
          '<li><i class="bi bi-wallet2" aria-hidden="true"></i><span>' + V.fcfa(k.total) + ' · ' + V.pluriel(r.voyageurs, 'voyageur') + '</span></li>' +
        '</ul>' + note +
      '</div>' +
      '<div class="resa-card__actions">' + actions + '</div>' +
    '</article>';
  }

  function afficher() {
    var onglet = document.querySelector('[name="onglet"]:checked').value;
    var liste = toutes.filter(function (r) { return groupes[onglet].indexOf(r.statut) >= 0; });
    $('liste').innerHTML = liste.length ? liste.map(carte).join('') :
      '<div class="empty-state" style="background:#fff"><i class="bi bi-ticket-perforated" aria-hidden="true"></i>' +
      '<h2 class="h4 mt-2">' + vides[onglet][0] + '</h2><p class="text-soft">' + vides[onglet][1] + '</p>' +
      '<a class="btn btn-primary" href="recherche.html">Chercher un logement</a></div>';
  }

  Object.keys(groupes).forEach(function (g) {
    var n = toutes.filter(function (r) { return groupes[g].indexOf(r.statut) >= 0; }).length;
    $('n-' + g).textContent = '(' + n + ')';
  });
  [].forEach.call(document.querySelectorAll('[name="onglet"]'), function (o) { o.addEventListener('change', afficher); });
  if (V.qs('onglet') && groupes[V.qs('onglet')]) { $('o-' + V.qs('onglet')).checked = true; }
  afficher();
});
