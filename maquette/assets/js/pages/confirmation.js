/* Confirmation : billet avec QR Code */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var V = window.VIVI;
  var $ = function (id) { return document.getElementById(id); };

  /* Sans réservation en cours (page ouverte directement), on montre un exemple */
  var r = V.lireReservation() || V.reservationParDefaut();
  if (!r.reference) { r.reference = 'VIVI-7K2M9P'; r.code = '482916'; r.moyen = 'MTN Mobile Money'; }
  var k = V.calculer(r), l = k.logement;

  $('c-reference').textContent = r.reference;
  $('c-titre').textContent = l.titre;
  $('c-lieu').textContent = l.quartier + ', ' + l.ville;
  $('c-date').textContent = V.dateLongue(r.date);
  $('c-creneau').textContent = k.creneau;
  $('c-voyageurs').textContent = V.pluriel(r.voyageurs, 'voyageur');
  $('c-services').textContent = k.lignesServices.length
    ? k.lignesServices.map(function (s) { return s.nom + (s.quantite > 1 ? ' × ' + s.quantite : ''); }).join(', ')
    : 'Aucun';
  $('c-total').textContent = V.fcfa(k.total);
  $('c-moyen').textContent = r.moyen;
  $('c-reporter').href = 'reporter.html?ref=' + encodeURIComponent(r.reference);
  $('c-annuler').href = 'annuler.html?ref=' + encodeURIComponent(r.reference);

  var code = $('c-code');
  code.setAttribute('aria-label', 'Code de secours : ' + r.code.split('').join(' '));
  code.innerHTML = r.code.split('').map(function (c) { return '<span aria-hidden="true">' + V.esc(c) + '</span>'; }).join('');

  /* En production, le QR Code est généré et signé par le serveur, à usage unique. */
  var zone = $('qr');
  if (window.qrcode) {
    var q = window.qrcode(0, 'M');
    q.addData('VIVI|' + r.reference + '|' + r.code);
    q.make();
    zone.innerHTML = q.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
  }

  $('enregistrer').addEventListener('click', function () {
    var svg = zone.querySelector('svg');
    if (!svg) { return; }
    var source = svg.outerHTML;
    if (source.indexOf('xmlns=') < 0) { source = source.replace('<svg', '<svg xmlns="http://www.w3.org/2000/svg"'); }
    var lien = document.createElement('a');
    lien.href = URL.createObjectURL(new Blob([source], { type: 'image/svg+xml' }));
    lien.download = r.reference + '.svg';
    document.body.appendChild(lien);
    lien.click();
    lien.remove();
    V.message('QR Code enregistré sur votre appareil.');
  });
});
