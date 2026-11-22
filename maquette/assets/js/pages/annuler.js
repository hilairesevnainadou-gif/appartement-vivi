/* Espace client : annulation d'une réservation (cahier des charges, section 6.1) */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var V = window.VIVI;
  var $ = function (id) { return document.getElementById(id); };

  var r = V.reservationClient(V.qs('ref'));
  var ref = encodeURIComponent(r.reference), lienDetail = 'ma-reservation.html?ref=' + ref;

  /* Seule une réservation confirmée et non reportée peut être annulée */
  if (r.statut !== 'confirmee') { window.location.replace(lienDetail); return; }

  var k = V.calculer(r), l = k.logement, b = V.remboursement(r);

  $('retour').href = lienDetail;
  $('an-garder').href = lienDetail;
  $('an-reporter').href = 'reporter.html?ref=' + ref;
  $('an-resume').textContent = l.titre + ' · ' + V.dateLongue(r.date) + ' · ' + k.creneau.charAt(0).toLowerCase() + k.creneau.slice(1);
  $('an-regle').textContent = b.regle;
  $('an-politique').textContent = b.politique;
  $('an-limite').textContent = V.dateLongue(V.limiteReport(r));
  $('an-bloc-report').hidden = !V.reportPossible(r);

  $('an-lignes').innerHTML =
    '<div><dt>Total payé</dt><dd>' + V.fcfa(b.paye) + '</dd></div>' +
    '<div><dt>Montant retenu</dt><dd>' + (b.retenue ? '− ' + V.fcfa(b.retenue) : V.fcfa(0)) + '</dd></div>' +
    '<div class="total"><dt>Remboursement</dt><dd>' + V.fcfa(b.montant) + '</dd></div>';
  $('an-delai').textContent = b.montant > 0
    ? 'Remboursement sur votre compte ' + r.moyen + ', sous 24 h à 7 jours.'
    : 'Aucun remboursement ne sera effectué. Le report, lui, reste possible.';
  $('an-m-texte').textContent = b.montant > 0
    ? 'Vous serez remboursé de ' + V.fcfa(b.montant) + '.'
    : 'Vous ne serez pas remboursé.';

  $('an-oui').addEventListener('click', function () {
    V.modifierReservation(r.reference, { statut: 'annulee', rembourse: b.montant, motif: $('an-motif').value });
    var modale = window.bootstrap.Modal.getInstance($('confirmer'));
    if (modale) { modale.hide(); }
    $('an-f-texte').textContent = b.montant > 0
      ? 'Vous serez remboursé de ' + V.fcfa(b.montant) + ' sur votre compte ' + r.moyen + ', sous 24 h à 7 jours. Un SMS vous le confirme.'
      : 'Aucun remboursement n’est dû pour cette annulation. Un SMS vous le confirme.';
    $('vue-choix').hidden = true;
    $('vue-fait').hidden = false;
    window.scrollTo(0, 0);
  });
});
