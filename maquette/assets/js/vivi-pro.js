/* ==========================================================================
   Appartement VIVI — maquette : fonctions communes de l'espace propriétaire.
   Le menu latéral, la barre haute et la navigation basse sont injectés ici
   (futurs partiels Blade du gabarit « propriétaire »).
   ========================================================================== */
(function () {
  'use strict';

  var D = window.VIVI_DATA, V = window.VIVI, O = D.proprietaire;
  var P = window.VIVI_PRO = {};

  /* ---------- Données ---------- */
  P.logements = function () {
    return O.logements.map(function (x) {
      return Object.assign({}, V.logement(x.id), { statut: x.statut, enLigne: x.enLigne });
    });
  };
  P.estMien = function (logementId) { return O.logements.some(function (x) { return x.id === logementId; }); };

  /* QR Codes scannés pendant la visite de la maquette */
  var CLE_SCANS = 'vivi.pro.scans';
  P.scans = function () {
    try { return JSON.parse(window.sessionStorage.getItem(CLE_SCANS)) || []; } catch (e) { return []; }
  };
  P.marquerScan = function (reference) {
    var s = P.scans();
    if (s.indexOf(reference) < 0) { s.push(reference); }
    try { window.sessionStorage.setItem(CLE_SCANS, JSON.stringify(s)); } catch (e) { /* sans gravité */ }
  };

  P.reservations = function () {
    var scans = P.scans();
    var liste = O.reservations.map(function (x) {
      var r = Object.assign({}, x, { date: V.ajouterJours(V.aujourdhui(), x.dans) });
      if (r.etat === 'attendu' && scans.indexOf(r.reference) >= 0) { r.etat = 'en-cours'; }
      return r;
    });
    /* Une réservation faite côté client pendant la visite apparaît ici si elle concerne un de ses logements */
    var s = V.lireReservation();
    if (s && s.reference && s.statut !== 'annulee' && P.estMien(s.logement)) {
      var mots = (s.nom || 'Client VIVI').split(/\s+/);
      liste.push(Object.assign({}, s, {
        client: mots[0] + (mots[1] ? ' ' + mots[1].charAt(0) + '.' : ''),
        etat: scans.indexOf(s.reference) >= 0 ? 'en-cours' : 'attendu'
      }));
    }
    return liste.sort(function (a, b) { return V.debutSejour(a) - V.debutSejour(b); });
  };

  P.etats = {
    'attendu':  { label: 'Attendu', classe: 'chip--orange', icone: 'bi-hourglass-split' },
    'en-cours': { label: 'Séjour en cours', classe: 'chip--success', icone: 'bi-door-open' },
    'termine':  { label: 'Terminé', classe: 'chip--line', icone: 'bi-flag' }
  };
  P.pastille = function (etat) {
    var e = P.etats[etat] || P.etats.attendu;
    return '<span class="chip ' + e.classe + '"><i class="bi ' + e.icone + '" aria-hidden="true"></i>' + e.label + '</span>';
  };
  /* Heure de début et durée, pour les listes */
  P.horaire = function (r) {
    var c = V.logement(r.logement).creneaux;
    if (r.formule === 'heure') { return { debut: V.heure(r.heureDebut), fin: V.heure(r.heureDebut + r.duree), duree: r.duree + ' h' }; }
    if (r.formule === 'demi') {
      var d = c.demi.filter(function (x) { return x.id === r.demi; })[0] || c.demi[0];
      return { debut: d.de, fin: d.a, duree: d.label };
    }
    return { debut: c.jour.arrivee, fin: c.jour.depart, duree: V.pluriel(r.jours, 'jour') };
  };
  P.servicesDe = function (r) {
    return Object.keys(r.services || {}).filter(function (id) { return r.services[id] > 0 && V.service(id); })
      .map(function (id) { return V.service(id).nom + (r.services[id] > 1 ? ' × ' + r.services[id] : ''); });
  };
  /* Montant qui revient au propriétaire : la location. La commission, payée par le client, revient à VIVI. */
  P.revenu = function (r) { return V.calculer(r).location; };

  /* ---------- Structure de page ---------- */
  var MENU = [
    { id: 'tableau', href: 'pro-tableau-de-bord.html', label: 'Tableau de bord', icone: 'bi-speedometer2' },
    { id: 'logements', href: 'pro-logements.html', label: 'Mes logements', icone: 'bi-houses' },
    { id: 'calendrier', href: 'pro-calendrier.html', label: 'Calendrier', icone: 'bi-calendar3' },
    { id: 'scanner', href: 'pro-scanner.html', label: 'Scanner un QR Code', icone: 'bi-qr-code-scan' },
    { id: 'reservations', bientot: 'Toutes les réservations', label: 'Réservations', icone: 'bi-ticket-perforated' },
    { id: 'revenus', bientot: 'Revenus et retraits', label: 'Revenus', icone: 'bi-cash-stack' },
    { id: 'gestionnaires', bientot: 'Gestionnaires délégués', label: 'Gestionnaires', icone: 'bi-person-gear' },
    { id: 'abonnement', href: 'pro-abonnement.html', label: 'Abonnement', icone: 'bi-credit-card-2-front' }
  ];
  function lien(m, page) {
    var corps = '<i class="bi ' + m.icone + '" aria-hidden="true"></i>' + m.label;
    return m.href
      ? '<li><a href="' + m.href + '"' + (m.id === page ? ' aria-current="page"' : '') + '>' + corps + '</a></li>'
      : '<li><a href="#" data-bientot="' + m.bientot + '">' + corps + '<span class="soon">bientôt</span></a></li>';
  }

  P.miseEnPage = function () {
    var corps = document.body, page = corps.dataset.page || '';

    var cote = document.getElementById('pro-menu');
    if (cote) {
      cote.innerHTML =
        '<a class="pro-side__brand" href="pro-tableau-de-bord.html" aria-label="VIVI, tableau de bord">' +
          '<img src="assets/img/logo/vivi-symbole-blanc.svg" alt="" width="40" height="40">' +
          '<span class="vivi-brand__text"><b>VIVI</b><small>Appartements<br>meublés.</small></span></a>' +
        '<div class="pro-side__label">Espace propriétaire</div>' +
        '<nav aria-label="Menu propriétaire"><ul class="pro-nav">' + MENU.map(function (m) { return lien(m, page); }).join('') + '</ul></nav>' +
        '<div class="pro-side__foot">' +
          '<div class="pro-user"><span class="avatar" aria-hidden="true">' + O.initiales + '</span><span><strong>' + V.esc(O.nom) + '</strong><small>Propriétaire</small></span></div>' +
          '<a class="retour" href="index.html"><i class="bi bi-box-arrow-left" aria-hidden="true"></i>Retour au site client</a>' +
        '</div>';
    }

    var haut = document.getElementById('pro-haut');
    if (haut) {
      haut.innerHTML =
        '<a class="vivi-brand" href="pro-tableau-de-bord.html" aria-label="Tableau de bord"><img src="assets/img/logo/vivi-symbole.svg" alt="" width="36" height="36"></a>' +
        '<span class="pro-top__titre">' + V.esc(corps.dataset.titre || 'Espace propriétaire') + '</span>' +
        '<span class="avatar" style="width:36px;height:36px;font-size:.85rem;background:var(--vivi-orange-400);color:var(--vivi-ink)" aria-label="' + V.esc(O.nom) + '">' + O.initiales + '</span>';
    }

    var basse = document.getElementById('pro-nav-basse');
    if (basse) {
      var c = function (id) { return id === page ? ' aria-current="page"' : ''; };
      basse.className = 'pro-bottomnav';
      basse.setAttribute('aria-label', 'Navigation propriétaire');
      basse.innerHTML =
        '<a href="pro-tableau-de-bord.html"' + c('tableau') + '><i class="bi bi-speedometer2" aria-hidden="true"></i>Accueil</a>' +
        '<a href="pro-logements.html"' + c('logements') + '><i class="bi bi-houses" aria-hidden="true"></i>Logements</a>' +
        '<a class="scan" href="pro-scanner.html"' + c('scanner') + '><span class="rond"><i class="bi bi-qr-code-scan" aria-hidden="true"></i></span>Scanner</a>' +
        '<a href="pro-calendrier.html"' + c('calendrier') + '><i class="bi bi-calendar3" aria-hidden="true"></i>Calendrier</a>' +
        '<button type="button" data-bs-toggle="offcanvas" data-bs-target="#pro-plus" aria-controls="pro-plus"><i class="bi bi-list" aria-hidden="true"></i>Plus</button>';

      var plus = document.createElement('div');
      plus.className = 'offcanvas offcanvas-bottom pro-plus';
      plus.id = 'pro-plus';
      plus.tabIndex = -1;
      plus.setAttribute('aria-labelledby', 'pro-plus-titre');
      plus.style.height = 'auto';
      plus.style.borderRadius = '20px 20px 0 0';
      plus.innerHTML =
        '<div class="offcanvas-header"><h2 class="offcanvas-title h5 mb-0" id="pro-plus-titre">' + V.esc(O.nom) + '</h2>' +
          '<button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fermer"></button></div>' +
        '<div class="offcanvas-body pt-0"><ul class="pro-nav">' + MENU.slice(4).map(function (m) { return lien(m, page); }).join('') +
          '<li><a href="index.html"><i class="bi bi-box-arrow-left" aria-hidden="true"></i>Retour au site client</a></li></ul></div>';
      document.body.appendChild(plus);
    }
  };

  document.addEventListener('DOMContentLoaded', P.miseEnPage);
})();
