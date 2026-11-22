/* Page de résultats : filtres appliqués en direct sur les données de la maquette */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var D = window.VIVI_DATA, V = window.VIVI;
  var $ = function (id) { return document.getElementById(id); };

  var filtres = $('form-filtres'), ville = $('b-ville'), date = $('b-date'), voyageurs = $('b-voyageurs');
  var tri = $('tri'), budget = $('f-budget'), budgetTexte = $('f-budget-texte');
  var BUDGETS = { heure: [2000, 6000, 500], demi: [6000, 20000, 1000], jour: [12000, 60000, 2000] };

  /* État initial repris de l'adresse (recherche lancée depuis l'accueil) */
  var villes = [].map.call(ville.options, function (o) { return o.value || o.text; });
  if (villes.indexOf(V.qs('ville')) > 0) { ville.value = V.qs('ville'); }
  date.min = V.aujourdhui();
  date.value = V.dateValide(V.qs('date')) && V.qs('date') >= V.aujourdhui() ? V.qs('date') : V.aujourdhui();
  var v = parseInt(V.qs('voyageurs'), 10);
  voyageurs.value = v >= 1 && v <= 6 ? v : 1;
  var f0 = V.qs('formule');
  if (D.formules[f0]) { filtres.querySelector('[name="formule"][value="' + f0 + '"]').checked = true; }

  function formule() { return filtres.querySelector('[name="formule"]:checked').value; }
  function coches(nom) {
    return [].map.call(filtres.querySelectorAll('[name="' + nom + '"]:checked'), function (c) { return c.value; });
  }

  /* Le budget dépend de la formule : il n'a de sens que pour une unité de prix */
  function reglerBudget() {
    var f = formule(), b = BUDGETS[f];
    budget.disabled = !b;
    if (b) { budget.min = b[0]; budget.max = b[1]; budget.step = b[2]; budget.value = b[1]; }
    ecrireBudget();
  }
  function ecrireBudget() {
    var f = formule();
    if (!f) { budgetTexte.textContent = 'Choisissez une formule pour filtrer par budget.'; return; }
    budgetTexte.textContent = +budget.value >= +budget.max
      ? 'Tous les prix'
      : 'Jusqu’à ' + V.fcfa(+budget.value) + ' ' + D.formules[f].par;
  }

  function afficher() {
    var f = formule(), types = coches('type'), equips = coches('equip'), services = coches('service');
    var plafond = f && +budget.value < +budget.max ? +budget.value : null;

    var liste = D.logements.filter(function (l) {
      if (ville.value && l.ville !== ville.value) { return false; }
      if (l.capacite < +voyageurs.value) { return false; }
      if (f && l.prix[f] == null) { return false; }
      if (plafond !== null && l.prix[f] > plafond) { return false; }
      if (types.length && types.indexOf(l.type) < 0) { return false; }
      if (!equips.every(function (e) { return l.equipements.indexOf(e) >= 0; })) { return false; }
      if (!services.every(function (s) { return l.services.indexOf(s) >= 0; })) { return false; }
      return true;
    });

    var prixDe = function (l) { return f ? l.prix[f] : V.prixMin(l).prix; };
    if (tri.value === 'prix') { liste.sort(function (a, b) { return prixDe(a) - prixDe(b); }); }
    if (tri.value === 'note') { liste.sort(function (a, b) { return b.note - a.note; }); }

    $('titre-resultats').textContent = (liste.length ? V.pluriel(liste.length, 'logement') : 'Aucun logement') +
      (ville.value ? ' à ' + ville.value : ' au Bénin');
    $('sous-titre').textContent = V.dateCourte(date.value) + ' · ' +
      (f ? D.formules[f].label.toLowerCase() : 'toutes formules') + ' · ' + V.pluriel(+voyageurs.value, 'voyageur');

    var suite = '&date=' + date.value + '&voyageurs=' + voyageurs.value + (f ? '&formule=' + f : '');
    $('resultats').innerHTML = liste.length
      ? liste.map(function (l) { return V.carteLogement(l, { ligne: true, niveau: 2, formule: f, suite: suite }); }).join('')
      : '<div class="empty-state"><i class="bi bi-search" aria-hidden="true"></i>' +
        '<h2 class="h4 mt-2">Aucun logement ne correspond à ces critères</h2>' +
        '<p class="text-soft">Augmentez le budget, retirez un filtre ou choisissez une autre ville.</p>' +
        '<button class="btn btn-primary" type="button" id="vider">Réinitialiser les filtres</button></div>';

    var n = (f ? 1 : 0) + (plafond !== null ? 1 : 0) + types.length + equips.length + services.length;
    var pastille = $('nb-filtres');
    pastille.textContent = n;
    pastille.classList.toggle('d-none', n === 0);
    $('voir-resultats').textContent = liste.length ? 'Voir ' + V.pluriel(liste.length, 'logement') : 'Aucun logement';

    /* L'adresse reflète la recherche : elle peut être partagée */
    try {
      var p = new URLSearchParams();
      if (ville.value) { p.set('ville', ville.value); }
      if (f) { p.set('formule', f); }
      p.set('date', date.value); p.set('voyageurs', voyageurs.value);
      window.history.replaceState(null, '', '?' + p.toString());
    } catch (e) { /* sans gravité */ }
  }

  filtres.addEventListener('change', function (e) {
    if (e.target.name === 'formule') { reglerBudget(); }
    afficher();
  });
  budget.addEventListener('input', function () { ecrireBudget(); afficher(); });
  filtres.addEventListener('reset', function () { setTimeout(function () { reglerBudget(); afficher(); }, 0); });
  $('barre').addEventListener('change', afficher);
  $('barre').addEventListener('submit', function (e) { e.preventDefault(); });
  tri.addEventListener('change', afficher);
  $('resultats').addEventListener('click', function (e) {
    if (e.target.id === 'vider') { ville.value = ''; voyageurs.value = 1; filtres.reset(); }
  });

  reglerBudget();
  afficher();
});
