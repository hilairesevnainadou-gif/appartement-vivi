/* Page d'accueil */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var D = window.VIVI_DATA, V = window.VIVI;

  /* Logements à la une */
  document.getElementById('a-la-une').innerHTML = D.logements.slice(0, 6).map(function (l) {
    return '<div class="col-12 col-sm-6 col-lg-4">' + V.carteLogement(l) + '</div>';
  }).join('');

  /* Services à la carte */
  document.getElementById('grille-services').innerHTML = D.services.map(function (s) {
    return '<div class="col-12 col-md-6 col-lg-4"><div class="service-tile">' +
      '<span class="service-tile__icon"><i class="bi ' + s.icone + '" aria-hidden="true"></i></span>' +
      '<div><h3>' + V.esc(s.nom) + '</h3><p>' + V.esc(s.desc) + '</p>' +
      '<p class="tarif">' + V.fcfa(s.prix) + ' <span>' + V.esc(s.unite) + '</span></p></div></div></div>';
  }).join('');

  /* Villes, avec le nombre de logements */
  var villes = {};
  D.logements.forEach(function (l) { villes[l.ville] = (villes[l.ville] || 0) + 1; });
  document.getElementById('grille-villes').innerHTML = Object.keys(villes).map(function (v) {
    return '<a class="place-link" href="recherche.html?ville=' + encodeURIComponent(v) + '">' +
      '<span>' + V.esc(v) + '<small>' + V.pluriel(villes[v], 'logement') + '</small></span>' +
      '<i class="bi bi-chevron-right" aria-hidden="true"></i></a>';
  }).join('');

  /* Date : aujourd'hui par défaut, pas de date passée */
  var date = document.getElementById('r-date');
  date.min = V.aujourdhui();
  date.value = V.aujourdhui();
});
