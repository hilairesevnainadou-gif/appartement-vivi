/* Espace propriétaire : abonnement (formules d'exemple) */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var D = window.VIVI_DATA, V = window.VIVI, O = D.proprietaire;
  var $ = function (id) { return document.getElementById(id); };
  var aujourdhui = V.aujourdhui();

  var actuelle = D.abonnements.filter(function (a) { return a.id === O.abonnement.formule; })[0];
  var publies = O.logements.filter(function (x) { return x.statut === 'publie'; }).length;
  var echeance = V.ajouterJours(aujourdhui, O.abonnement.echeanceDans);

  $('ab-nom').textContent = actuelle.nom;
  $('ab-prix').textContent = V.fcfa(actuelle.prix);
  $('ab-moyen').textContent = O.abonnement.moyen;
  $('ab-echeance').textContent = 'Prochain renouvellement le ' + V.dateLongue(echeance) + ', dans ' + V.pluriel(O.abonnement.echeanceDans, 'jour') + '.';
  $('ab-quota').textContent = publies + ' sur ' + actuelle.logements;
  $('ab-jauge').style.width = Math.min(100, publies / actuelle.logements * 100) + '%';

  $('ab-formules').innerHTML = D.abonnements.map(function (a) {
    var courante = a.id === actuelle.id, tropPetite = a.logements < publies;
    return '<article class="plan' + (courante ? ' is-current' : '') + '">' +
      (courante ? '<span class="chip chip--teal">Votre formule</span>' : '') +
      '<h3>' + V.esc(a.nom) + '</h3>' +
      '<p class="plan__prix mb-0">' + V.fcfa(a.prix) + ' <small>par mois</small></p>' +
      '<ul>' + a.points.map(function (p) { return '<li><i class="bi bi-check-lg" aria-hidden="true"></i><span>' + V.esc(p) + '</span></li>'; }).join('') + '</ul>' +
      (courante
        ? '<button class="btn btn-ghost" type="button" disabled>Formule actuelle</button>'
        : tropPetite
          ? '<button class="btn btn-ghost" type="button" disabled>Trop de logements publiés</button>'
          : '<button class="btn btn-outline-primary" type="button" data-formule="' + V.esc(a.nom) + '">Choisir ' + V.esc(a.nom) + '</button>') +
    '</article>';
  }).join('');

  $('ab-formules').addEventListener('click', function (e) {
    var bouton = e.target.closest('[data-formule]');
    if (bouton) { V.message('Maquette : le passage à la formule ' + bouton.dataset.formule + ' sera branché sur le paiement.'); }
  });

  /* Trois derniers prélèvements mensuels */
  var lignes = '';
  for (var i = 1; i <= 3; i++) {
    var d = new Date(echeance + 'T12:00:00'); d.setMonth(d.getMonth() - i);
    lignes += '<tr><td>' + new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }).format(d) + '</td>' +
      '<td>' + V.esc(actuelle.nom) + '</td>' +
      '<td><span class="chip chip--success"><i class="bi bi-check-circle" aria-hidden="true"></i>Payé</span></td>' +
      '<td class="num">' + V.fcfa(actuelle.prix) + '</td></tr>';
  }
  $('ab-paiements').innerHTML = lignes;
});
