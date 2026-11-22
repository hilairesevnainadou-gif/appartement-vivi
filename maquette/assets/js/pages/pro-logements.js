/* Espace propriétaire : liste des logements */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var D = window.VIVI_DATA, V = window.VIVI, P = window.VIVI_PRO, O = D.proprietaire;
  var $ = function (id) { return document.getElementById(id); };

  var logements = P.logements();
  var formule = D.abonnements.filter(function (a) { return a.id === O.abonnement.formule; })[0];
  var publies = logements.filter(function (l) { return l.statut === 'publie'; }).length;

  $('lg-quota').textContent = V.pluriel(publies, 'logement publié', 'logements publiés') + ' sur ' + formule.logements;
  $('lg-quota-detail').textContent = 'Inclus dans votre formule ' + formule.nom + '.';
  $('lg-jauge').style.width = Math.min(100, publies / formule.logements * 100) + '%';

  var noms = { heure: 'Heure', demi: 'Demi-journée', jour: 'Journée' };

  $('lg-liste').innerHTML = logements.map(function (l) {
    var publie = l.statut === 'publie', id = encodeURIComponent(l.id);
    var prix = V.formulesDe(l).map(function (f) { return '<li>' + noms[f] + ' <b>' + V.fcfa(l.prix[f]) + '</b></li>'; }).join('');
    var etat = publie
      ? '<span class="chip chip--success"><i class="bi bi-check-circle" aria-hidden="true"></i>Publié</span>'
      : '<span class="chip chip--orange"><i class="bi bi-hourglass-split" aria-hidden="true"></i>En attente de validation</span>';
    return '<article class="pro-listing">' +
      '<img src="' + V.photo(l.photos[0], 640) + '" alt="" width="640" height="480" loading="lazy">' +
      '<div>' +
        '<div class="d-flex flex-wrap gap-2">' + etat + '<span class="chip chip--line">' + V.esc(l.type) + '</span></div>' +
        '<h2>' + V.esc(l.titre) + '</h2>' +
        '<p class="mb-0 text-soft small"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>' + V.esc(l.quartier + ', ' + l.ville) + ' · ' + V.pluriel(l.capacite, 'voyageur') + '</p>' +
        '<ul class="pro-listing__prix">' + prix + '</ul>' +
        (publie ? '' : '<div class="note-box note-box--orange mt-2" style="font-size:.9rem;padding:10px 12px"><i class="bi bi-info-circle" aria-hidden="true"></i><span>VIVI vérifie les informations et les photos avant la mise en ligne.</span></div>') +
      '</div>' +
      '<div class="pro-listing__actions">' +
        '<div class="form-check form-switch">' +
          '<input class="form-check-input" type="checkbox" role="switch" id="ligne-' + l.id + '"' + (l.enLigne ? ' checked' : '') + (publie ? '' : ' disabled') + ' data-titre="' + V.esc(l.titre) + '">' +
          '<label class="form-check-label" for="ligne-' + l.id + '">' + (publie ? 'Visible dans la recherche' : 'Pas encore visible') + '</label></div>' +
        '<a class="btn btn-primary btn-sm" href="pro-logement.html?id=' + id + '"><i class="bi bi-pencil" aria-hidden="true"></i>Modifier</a>' +
        '<a class="btn btn-ghost btn-sm" href="pro-calendrier.html?id=' + id + '"><i class="bi bi-calendar3" aria-hidden="true"></i>Calendrier</a>' +
        (publie ? '<a class="btn btn-ghost btn-sm" href="logement.html?id=' + id + '"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>Voir l’annonce</a>' : '') +
      '</div>' +
    '</article>';
  }).join('');

  $('lg-liste').addEventListener('change', function (e) {
    if (e.target.matches('.form-switch input')) {
      V.message(e.target.checked
        ? '« ' + e.target.dataset.titre + ' » est de nouveau visible dans la recherche.'
        : '« ' + e.target.dataset.titre + ' » est mis en pause : il n’apparaît plus dans la recherche.');
    }
  });
});
