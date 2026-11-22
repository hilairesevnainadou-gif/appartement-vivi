/* Espace propriétaire : tableau de bord */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var D = window.VIVI_DATA, V = window.VIVI, P = window.VIVI_PRO, O = D.proprietaire;
  var $ = function (id) { return document.getElementById(id); };
  var aujourdhui = V.aujourdhui();
  var nombre = new Intl.NumberFormat('fr-FR');

  $('tb-date').textContent = V.dateLongue(aujourdhui);
  $('tb-bonjour').textContent = 'Bonjour ' + O.prenom;

  var toutes = P.reservations();
  var duJour = toutes.filter(function (r) { return r.date === aujourdhui; });
  var aVenir = toutes.filter(function (r) { return r.date > aujourdhui && r.date <= V.ajouterJours(aujourdhui, 7); });

  /* ---------- Indicateurs ---------- */
  function ecart(valeur, avant, unite) {
    var haut = valeur >= avant;
    var texte = unite === 'pts'
      ? Math.abs(valeur - avant) + ' pts'
      : nombre.format(Math.round(Math.abs(valeur - avant) / avant * 1000) / 10) + ' %';
    return '<b class="' + (haut ? 'up' : 'down') + '"><i class="bi ' + (haut ? 'bi-arrow-up-short' : 'bi-arrow-down-short') + '" aria-hidden="true"></i>' +
      '<span class="visually-hidden">' + (haut ? 'en hausse de ' : 'en baisse de ') + '</span>' + texte + '</b> par rapport au mois dernier';
  }
  var enCours = duJour.filter(function (r) { return r.etat === 'en-cours'; }).length;
  $('k-arrivees').textContent = duJour.length;
  $('k-arrivees-detail').textContent = enCours ? 'dont ' + enCours + ' déjà sur place' : 'aucune sur place pour l’instant';
  $('k-avenir').textContent = aVenir.length;
  $('k-revenus').innerHTML = nombre.format(O.revenusMois).replace(/[  ]/g, ' ') + ' <small>FCFA</small>';
  $('k-revenus-delta').innerHTML = ecart(O.revenusMois, O.revenusMoisPrecedent);
  $('k-occupation').innerHTML = O.occupation + ' <small>%</small>';
  $('k-occupation-delta').innerHTML = ecart(O.occupation, O.occupationPrecedente, 'pts');

  /* ---------- Listes ---------- */
  function ligne(r, avecDate) {
    var l = V.logement(r.logement), h = P.horaire(r), services = P.servicesDe(r);
    var quand = avecDate
      ? '<div class="quand">' + new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short' }).format(new Date(r.date + 'T12:00:00')) + '<small>' + h.debut + '</small></div>'
      : '<div class="quand">' + h.debut + '<small>' + h.duree + '</small></div>';
    var action = '';
    if (!avecDate) {
      action = r.etat === 'attendu'
        ? '<a class="btn btn-primary btn-sm" href="pro-scanner.html?ref=' + encodeURIComponent(r.reference) + '"><i class="bi bi-qr-code-scan" aria-hidden="true"></i>Scanner</a>'
        : '';
    }
    return '<li>' + quand +
      '<div class="qui"><strong>' + V.esc(r.client) + ' · ' + V.pluriel(r.voyageurs, 'voyageur') + '</strong>' +
        '<span>' + V.esc(l.titre) + ' · ' + D.formules[r.formule].label.toLowerCase() + (avecDate ? ', ' + h.duree.toLowerCase() : '') + '</span>' +
        (services.length ? '<span><i class="bi bi-bag-check me-1" aria-hidden="true"></i>À préparer : ' + V.esc(services.join(', ')) + '</span>' : '') +
        (avecDate ? '' : P.pastille(r.etat)) + '</div>' +
      '<div class="action"><span class="montant">' + V.fcfa(P.revenu(r)) + '</span>' + action + '</div></li>';
  }
  var vide = function (texte) { return '<li style="display:block" class="text-soft">' + texte + '</li>'; };
  $('tb-arrivees').innerHTML = duJour.length ? duJour.map(function (r) { return ligne(r, false); }).join('') : vide('Aucune arrivée prévue aujourd’hui.');
  $('tb-prochaines').innerHTML = aVenir.length ? aVenir.map(function (r) { return ligne(r, true); }).join('') : vide('Aucune réservation sur les 7 prochains jours.');

  /* Solde et retraits : la caissière ou l'administrateur valide chaque retrait et indique le mode de règlement */
  var solde = O.solde, retraits = O.retraits.slice();
  function quand(dans) { return dans === 0 ? 'Auj.' : dans === -1 ? 'Hier' : new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short' }).format(new Date(V.ajouterJours(aujourdhui, dans) + 'T12:00:00')); }
  function afficherRetraits() {
    $('tb-solde').innerHTML = nombre.format(solde).replace(/[\u202f\u00a0]/g, '\u00a0') + ' <small>FCFA</small>';
    $('tb-retrait').disabled = solde <= 0;
    $('tb-retraits').innerHTML = retraits.map(function (v) {
      var paye = v.etat === 'paye';
      return '<li><div class="quand">' + quand(v.dans) + '</div>' +
        '<div class="qui"><strong class="tnum">' + V.esc(v.reference) + '</strong>' +
          '<span class="chip ' + (paye ? 'chip--success' : 'chip--orange') + '"><i class="bi ' + (paye ? 'bi-check-circle' : 'bi-hourglass-split') + '" aria-hidden="true"></i>' +
          (paye ? 'Payé · ' + V.esc(v.mode) : 'En attente de validation') + '</span></div>' +
        '<div class="action"><span class="montant">' + V.fcfa(v.montant) + '</span></div></li>';
    }).join('');
  }
  $('tb-retrait').addEventListener('click', function () {
    retraits.unshift({ reference: 'RET-0014', dans: 0, montant: solde, etat: 'en-attente' });
    V.message('Demande de retrait de ' + V.fcfa(solde) + ' envoyée. La caissière VIVI la validera et indiquera le mode de règlement.');
    solde = 0;
    afficherRetraits();
  });
  afficherRetraits();

  /* ---------- Abonnement ---------- */
  var formule = D.abonnements.filter(function (a) { return a.id === O.abonnement.formule; })[0];
  $('tb-formule').textContent = formule.nom;
  $('tb-echeance').textContent = 'Prochain renouvellement le ' + V.dateLongue(V.ajouterJours(aujourdhui, O.abonnement.echeanceDans)) +
    ' · ' + O.logements.filter(function (x) { return x.statut === 'publie'; }).length + ' logements publiés sur ' + formule.logements + ' inclus.';

  /* ---------- Graphique : une série, colonnes fines, étiquette sur le maximum seulement ---------- */
  var valeurs = O.revenus7j, max = Math.max.apply(null, valeurs);
  var pas = 20000, plafond = Math.ceil(max / pas) * pas;
  var jours = valeurs.map(function (_, i) { return V.ajouterJours(aujourdhui, i - (valeurs.length - 1)); });
  var court = function (iso) { return new Intl.DateTimeFormat('fr-FR', { weekday: 'short' }).format(new Date(iso + 'T12:00:00')); };

  var graduations = [];
  for (var g = 0; g <= plafond; g += pas) { graduations.push(g); }
  $('ch-y').innerHTML = graduations.map(function (v) { return '<span style="bottom:' + (v / plafond * 100) + '%">' + (v ? v / 1000 + ' k' : '0') + '</span>'; }).join('');
  $('ch-grille').innerHTML = graduations.map(function (v) { return '<i style="bottom:' + (v / plafond * 100) + '%"></i>'; }).join('');
  $('ch-colonnes').innerHTML = valeurs.map(function (v, i) {
    var h = v / plafond * 100;
    return '<button type="button" class="chart__col" data-i="' + i + '" aria-label="' + V.dateLongue(jours[i]) + ' : ' + V.fcfa(v) + '">' +
      (v === max ? '<span class="chart__val" style="top:' + (100 - h) + '%">' + nombre.format(v).replace(/[  ]/g, ' ') + '</span>' : '') +
      '<span class="chart__bar" style="height:' + h + '%"></span></button>';
  }).join('');
  $('ch-x').innerHTML = jours.map(function (iso, i) { return i === jours.length - 1 ? '<b>auj.</b>' : '<span>' + court(iso) + '</span>'; }).join('');
  $('ch-tableau').innerHTML = jours.map(function (iso, i) { return '<tr><th scope="row">' + V.dateCourte(iso) + '</th><td>' + V.fcfa(valeurs[i]) + '</td></tr>'; }).join('');
  $('tb-total7').textContent = V.fcfa(valeurs.reduce(function (t, v) { return t + v; }, 0));

  /* Infobulle au survol et au clavier ; les chiffres restent lisibles dans le tableau */
  var bulle = $('ch-bulle'), zone = $('ch-colonnes');
  function montrer(col) {
    var i = +col.dataset.i, h = valeurs[i] / plafond * 100;
    bulle.innerHTML = '<span class="premiere-majuscule">' + V.dateCourte(jours[i]) + '</span><strong>' + V.fcfa(valeurs[i]) + '</strong>';
    bulle.style.left = (col.offsetLeft + col.offsetWidth / 2) + 'px';
    bulle.style.top = (100 - h) + '%';
    bulle.hidden = false;
  }
  function cacher() { bulle.hidden = true; }
  [].forEach.call(zone.querySelectorAll('.chart__col'), function (col) {
    col.addEventListener('mouseenter', function () { montrer(col); });
    col.addEventListener('focus', function () { montrer(col); });
    col.addEventListener('click', function () { montrer(col); });
    col.addEventListener('mouseleave', cacher);
    col.addEventListener('blur', cacher);
  });
});
