/* Espace client : détail d'une réservation */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var V = window.VIVI;
  var $ = function (id) { return document.getElementById(id); };

  var r = V.reservationClient(V.qs('ref'));
  var k = V.calculer(r), l = k.logement, ref = encodeURIComponent(r.reference);
  var aVenir = r.statut === 'confirmee' || r.statut === 'reportee';

  document.title = l.titre + ' · Ma réservation · Appartement VIVI';
  $('m-titre').textContent = l.titre;
  $('m-statut').innerHTML = V.pastille(r.statut);
  $('m-lieu').textContent = l.quartier + ', ' + l.ville;
  $('m-reference').textContent = r.reference;
  $('m-date').textContent = V.dateLongue(r.date);
  $('m-creneau').textContent = k.creneau;
  $('m-voyageurs').textContent = V.pluriel(r.voyageurs, 'voyageur');
  $('m-services').textContent = k.lignesServices.length
    ? k.lignesServices.map(function (s) { return s.nom + (s.quantite > 1 ? ' × ' + s.quantite : ''); }).join(', ')
    : 'Aucun';
  $('m-annonce').href = 'logement.html?id=' + encodeURIComponent(l.id);

  /* Le QR Code n'est montré que pour un séjour à venir */
  if (aVenir) {
    V.dessinerQr($('qr'), r);
    V.codeSecours($('m-code'), r.code);
  } else {
    $('m-billet').classList.add('ticket--sans-qr');
  }

  $('m-lignes').innerHTML =
    '<div><dt>' + V.esc(k.detail) + '</dt><dd>' + V.fcfa(k.location) + '</dd></div>' +
    k.lignesServices.map(function (s) {
      return '<div><dt>' + V.esc(s.nom) + (s.quantite > 1 ? ' × ' + s.quantite : '') + '</dt><dd>' + V.fcfa(s.montant) + '</dd></div>';
    }).join('') +
    '<div><dt>Commission VIVI</dt><dd>' + V.fcfa(k.frais) + '</dd></div>' +
    '<div class="total"><dt>Total payé</dt><dd>' + V.fcfa(k.total) + '</dd></div>';
  $('m-moyen').textContent = 'Payé par ' + r.moyen + '. Un reçu vous a été envoyé par SMS.';

  /* Message et actions selon l'état de la réservation */
  var alerte = '', gerer = '';
  if (aVenir) {
    if (r.dateInitiale) {
      alerte = '<div class="note-box"><i class="bi bi-arrow-repeat" aria-hidden="true"></i><span>Réservation reportée. La date prévue au départ était le ' + V.dateLongue(r.dateInitiale) + '. Elle n’est plus remboursable.</span></div>';
    }
    gerer = (V.reportPossible(r)
        ? '<p>Un empêchement ? Vous pouvez reporter votre réservation jusqu’au ' + V.momentFinReport(r) + ' (3 h avant l’arrivée), ou l’annuler.</p>'
        : '<p>Vous ne pouvez plus reporter vous-même : il reste moins de 3 h avant l’arrivée. Seuls le propriétaire ou VIVI peuvent encore déplacer cette réservation : contactez-les.</p>') +
      '<div class="d-grid gap-2">' +
        (V.reportPossible(r) ? '<a class="btn btn-outline-primary" href="reporter.html?ref=' + ref + '"><i class="bi bi-arrow-repeat" aria-hidden="true"></i>Reporter</a>' : '') +
        (r.dateInitiale ? '' : '<a class="btn btn-outline-danger" href="annuler.html?ref=' + ref + '">Annuler la réservation</a>') +
        '<a class="btn btn-ghost" href="#" data-bientot="Contacter le propriétaire"><i class="bi bi-telephone" aria-hidden="true"></i>Contacter le propriétaire</a>' +
      '</div>';
  } else if (r.statut === 'terminee') {
    gerer = '<p>Ce séjour est terminé. Merci d’avoir réservé avec VIVI.</p>' +
      '<a class="btn btn-primary w-100" href="logement.html?id=' + encodeURIComponent(l.id) + '">Réserver à nouveau</a>';
    $('avis').hidden = false;
    if (r.noteDonnee) { $('form-avis').hidden = true; $('avis-merci').hidden = false; }
  } else {
    var rendu = typeof r.rembourse === 'number' ? r.rembourse : k.total;
    alerte = '<div class="note-box"><i class="bi bi-cash-coin" aria-hidden="true"></i><span>Réservation annulée. ' +
      (rendu > 0 ? 'Remboursement de <strong>' + V.fcfa(rendu) + '</strong> sur votre compte ' + V.esc(r.moyen) + ', sous 24 h à 7 jours.' : 'Aucun remboursement n’est dû.') + '</span></div>';
    gerer = '<p>Cette réservation est annulée.</p>' +
      '<a class="btn btn-primary w-100" href="logement.html?id=' + encodeURIComponent(l.id) + '">Réserver à nouveau</a>';
  }
  $('m-alerte').innerHTML = alerte;
  $('m-gerer').innerHTML = gerer;

  /* Avis */
  $('form-avis').addEventListener('submit', function (e) {
    e.preventDefault();
    var choix = document.querySelector('[name="note"]:checked');
    $('note-erreur').style.display = choix ? '' : 'block';
    if (!choix) { $('n5').focus(); return; }
    V.modifierReservation(r.reference, { noteDonnee: +choix.value });
    $('form-avis').hidden = true;
    $('avis-merci').hidden = false;
  });
});
