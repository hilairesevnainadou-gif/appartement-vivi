/* ==========================================================================
   Appartement VIVI — maquette : fonctions communes à toutes les pages.
   En-tête, pied de page et navigation basse sont injectés ici pour n'exister
   qu'à un seul endroit ; ils deviendront des partiels Blade.
   ========================================================================== */
(function () {
  'use strict';

  var D = window.VIVI_DATA;
  var V = window.VIVI = {};

  /* ---------- Outils ---------- */
  var nf = new Intl.NumberFormat('fr-FR');
  /* Espace insécable normale entre les milliers : plus lisible que l'espace fine */
  V.fcfa = function (n) { return nf.format(n).replace(/[  ]/g, ' ') + ' FCFA'; };
  V.note = function (n) { return n.toFixed(1).replace('.', ','); };
  V.qs = function (nom) { return new URLSearchParams(window.location.search).get(nom); };
  V.esc = function (s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };
  V.pluriel = function (n, mot, motPluriel) { return n + ' ' + (n > 1 ? (motPluriel || mot + 's') : mot); };
  V.heure = function (h) { h = ((h % 24) + 24) % 24; return (h < 10 ? '0' : '') + h + ':00'; };

  /* Dates : chaînes AAAA-MM-JJ en heure locale */
  function versDate(iso) { var p = iso.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
  function versIso(d) {
    var m = d.getMonth() + 1, j = d.getDate();
    return d.getFullYear() + '-' + (m < 10 ? '0' : '') + m + '-' + (j < 10 ? '0' : '') + j;
  }
  V.aujourdhui = function () { return versIso(new Date()); };
  V.dateValide = function (iso) { return /^\d{4}-\d{2}-\d{2}$/.test(iso || '') && !isNaN(versDate(iso)); };
  V.ajouterJours = function (iso, n) { var d = versDate(iso); d.setDate(d.getDate() + n); return versIso(d); };
  V.dateCourte = function (iso) {
    return new Intl.DateTimeFormat('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' }).format(versDate(iso));
  };
  V.dateLongue = function (iso) {
    return new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(versDate(iso));
  };

  /* ---------- Accès aux données ---------- */
  V.logement = function (id) {
    return D.logements.filter(function (l) { return l.id === id; })[0] || D.logements[0];
  };
  V.service = function (id) { return D.services.filter(function (s) { return s.id === id; })[0]; };
  V.photo = function (nom, largeur) { return 'assets/img/logements/' + nom + '-' + largeur + '.webp'; };
  V.formulesDe = function (l) {
    return ['heure', 'demi', 'jour'].filter(function (f) { return l.prix[f] != null; });
  };
  V.prixMin = function (l) { var f = V.formulesDe(l)[0]; return { formule: f, prix: l.prix[f] }; };
  V.unite = function (formule) { return { heure: '/ heure', demi: '/ demi-journée', jour: '/ jour' }[formule]; };

  /* ---------- Réservation en cours (gardée le temps de la session) ---------- */
  var CLE = 'vivi.reservation';
  V.reservationParDefaut = function (logementId) {
    var l = V.logement(logementId);
    var f = V.prixMin(l).formule;
    var r = { logement: l.id, formule: f, date: V.aujourdhui(), voyageurs: Math.min(2, l.capacite), services: {} };
    V.completerReservation(r);
    return r;
  };
  /* Remet la réservation dans les limites des créneaux du logement */
  V.completerReservation = function (r) {
    var l = V.logement(r.logement), c = l.creneaux;
    if (l.prix[r.formule] == null) { r.formule = V.prixMin(l).formule; }
    if (!V.dateValide(r.date) || r.date < V.aujourdhui()) { r.date = V.aujourdhui(); }
    r.voyageurs = Math.max(1, Math.min(l.capacite, r.voyageurs || 1));
    if (c.heure) {
      var min = c.heure.minimum;
      if (typeof r.heureDebut !== 'number') { r.heureDebut = Math.max(c.heure.debut, Math.min(14, c.heure.fin - min)); }
      r.heureDebut = Math.max(c.heure.debut, Math.min(c.heure.fin - min, r.heureDebut));
      r.duree = Math.max(min, Math.min(c.heure.fin - r.heureDebut, r.duree || Math.max(min, 3)));
    }
    if (c.demi) {
      var libres = c.demi.filter(function (x) { return !x.complet; });
      var ok = libres.some(function (x) { return x.id === r.demi; });
      if (!ok) { r.demi = (libres[libres.length > 1 ? 1 : 0] || c.demi[0]).id; }
    }
    r.jours = Math.max(1, Math.min(14, r.jours || 1));
    r.services = r.services || {};
    return r;
  };
  V.lireReservation = function () {
    try { var r = JSON.parse(window.sessionStorage.getItem(CLE)); return r && r.logement ? V.completerReservation(r) : null; }
    catch (e) { return null; }
  };
  V.ecrireReservation = function (r) {
    try { window.sessionStorage.setItem(CLE, JSON.stringify(r)); } catch (e) { /* stockage indisponible : la maquette reste utilisable */ }
  };

  /* Calcul du prix. En production, ce calcul est fait par le serveur. */
  V.calculer = function (r) {
    var l = V.logement(r.logement), c = l.creneaux;
    var location = 0, detail = '', creneau = '';
    if (r.formule === 'heure') {
      location = l.prix.heure * r.duree;
      detail = V.fcfa(l.prix.heure) + ' × ' + r.duree + ' h';
      creneau = 'De ' + V.heure(r.heureDebut) + ' à ' + V.heure(r.heureDebut + r.duree) + ' (' + r.duree + ' h)';
    } else if (r.formule === 'demi') {
      var d = c.demi.filter(function (x) { return x.id === r.demi; })[0] || c.demi[0];
      location = l.prix.demi;
      detail = 'Demi-journée · ' + d.label.toLowerCase();
      creneau = d.label + ', de ' + d.de + ' à ' + d.a;
    } else {
      location = l.prix.jour * r.jours;
      detail = V.fcfa(l.prix.jour) + ' × ' + V.pluriel(r.jours, 'jour');
      creneau = 'Arrivée à partir de ' + c.jour.arrivee + ', départ avant ' + c.jour.depart + ' le ' + V.dateCourte(V.ajouterJours(r.date, r.jours));
    }
    var lignes = Object.keys(r.services || {}).filter(function (id) { return r.services[id] > 0 && V.service(id); })
      .map(function (id) {
        var s = V.service(id);
        return { id: id, nom: s.nom, quantite: r.services[id], montant: s.prix * r.services[id] };
      });
    var services = lignes.reduce(function (t, x) { return t + x.montant; }, 0);
    var frais = Math.round((location + services) * D.tauxFraisService);
    return { logement: l, location: location, detail: detail, creneau: creneau, lignesServices: lignes, services: services, frais: frais, total: location + services + frais };
  };

  /* ---------- Espace client : mes réservations ---------- */
  V.statuts = {
    'confirmee':  { label: 'Confirmée', classe: 'chip--teal', icone: 'bi-check-circle' },
    'reportee':   { label: 'Reportée', classe: 'chip--teal', icone: 'bi-arrow-repeat' },
    'terminee':   { label: 'Terminée', classe: 'chip--line', icone: 'bi-flag' },
    'annulee':    { label: 'Annulée', classe: 'chip--line', icone: 'bi-x-circle' }
  };
  V.pastille = function (statut) {
    var s = V.statuts[statut] || V.statuts.confirmee;
    return '<span class="chip ' + s.classe + '"><i class="bi ' + s.icone + '" aria-hidden="true"></i>' + s.label + '</span>';
  };

  /* Les changements faits dans la maquette (report, annulation, avis) sont gardés le temps de la session */
  var CLE_MODIFS = 'vivi.modifs';
  function lireModifs() {
    try { return JSON.parse(window.sessionStorage.getItem(CLE_MODIFS)) || {}; } catch (e) { return {}; }
  }
  V.modifierReservation = function (reference, champs) {
    var s = V.lireReservation();
    if (s && s.reference === reference) { Object.assign(s, champs); V.ecrireReservation(s); return; }
    var m = lireModifs();
    m[reference] = Object.assign(m[reference] || {}, champs);
    try { window.sessionStorage.setItem(CLE_MODIFS, JSON.stringify(m)); } catch (e) { /* sans gravité */ }
  };
  V.reservationsClient = function () {
    var m = lireModifs();
    var liste = D.reservations.map(function (x) {
      return Object.assign({}, x, { date: V.ajouterJours(V.aujourdhui(), x.dans) }, m[x.reference] || {});
    });
    var s = V.lireReservation();   /* réservation payée pendant cette visite de la maquette */
    if (s && s.reference) { if (!s.statut) { s.statut = 'confirmee'; } liste.unshift(s); }
    return liste;
  };
  V.reservationClient = function (reference) {
    var liste = V.reservationsClient();
    return liste.filter(function (r) { return r.reference === reference; })[0] || liste[0];
  };

  /* Début du séjour, pour appliquer les délais d'annulation */
  V.debutSejour = function (r) {
    var c = V.logement(r.logement).creneaux, h = 12;
    if (r.formule === 'heure') { h = r.heureDebut; }
    else if (r.formule === 'demi') { h = parseInt((c.demi.filter(function (x) { return x.id === r.demi; })[0] || c.demi[0]).de, 10); }
    else { h = parseInt(c.jour.arrivee, 10); }
    var d = versDate(r.date); d.setHours(h, 0, 0, 0);
    return d;
  };
  /* Dernier jour pour reporter : 10 jours après la date prévue au départ */
  V.limiteReport = function (r) { return V.ajouterJours(r.dateInitiale || r.date, D.delaiReportJours); };
  /* Report : possible jusqu'à 3 h avant le début du séjour, vers une date libre dans les 10 jours */
  V.finReport = function (r) { return new Date(V.debutSejour(r).getTime() - D.reportDelaiMinHeures * 3600000); };
  V.reportPossible = function (r) { return (r.statut === 'confirmee' || r.statut === 'reportee') && new Date() < V.finReport(r); };
  V.momentFinReport = function (r) {
    var d = V.finReport(r);
    return new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }).format(d) + ' à ' +
      new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' }).format(d);
  };
  V.joursEntre = function (isoA, isoB) { return Math.round((versDate(isoB) - versDate(isoA)) / 86400000); };

  /* Remboursement en cas d'annulation. Lecture littérale du cahier des charges V1.0 (section 6.1) :
     Flexible pour l'heure et la demi-journée, Modérée pour la journée. En production : calcul côté serveur. */
  V.remboursement = function (r) {
    var k = V.calculer(r), l = k.logement;
    var heures = (V.debutSejour(r) - new Date()) / 3600000;
    var flexible = r.formule !== 'jour', retenue, regle;
    if (heures <= 0) {
      retenue = k.total; regle = 'L’heure d’arrivée est passée : la réservation n’est plus remboursable.';
    } else if (flexible && heures >= 24) {
      retenue = 0; regle = 'Vous annulez plus de 24 h avant l’arrivée : remboursement intégral.';
    } else if (flexible) {
      retenue = k.location; regle = 'Vous annulez moins de 24 h avant l’arrivée : la location reste due, les services et les frais vous sont remboursés.';
    } else if (heures >= 120) {
      retenue = 0; regle = 'Vous annulez plus de 5 jours avant l’arrivée : remboursement intégral.';
    } else {
      retenue = l.prix.jour + Math.round(l.prix.jour * 0.5 * (r.jours - 1));
      regle = 'Vous annulez moins de 5 jours avant l’arrivée : la première journée et la moitié des suivantes restent dues.';
    }
    return { politique: flexible ? 'Flexible' : 'Modérée', regle: regle, paye: k.total, retenue: retenue, montant: k.total - retenue };
  };

  /* QR Code de la réservation (en production : généré et signé par le serveur) */
  V.dessinerQr = function (zone, r) {
    if (!window.qrcode) { return; }
    var q = window.qrcode(0, 'M');
    q.addData('VIVI|' + r.reference + '|' + r.code);
    q.make();
    zone.innerHTML = q.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
  };
  V.codeSecours = function (zone, code) {
    zone.setAttribute('aria-label', 'Code de secours : ' + code.split('').join(' '));
    zone.innerHTML = code.split('').map(function (c) { return '<span aria-hidden="true">' + V.esc(c) + '</span>'; }).join('');
  };

  /* ---------- Carte logement ---------- */
  V.carteLogement = function (l, o) {
    o = o || {};
    var formule = o.formule && l.prix[o.formule] != null ? o.formule : V.prixMin(l).formule;
    var h = 'h' + (o.niveau || 3);
    var lien = 'logement.html?id=' + encodeURIComponent(l.id) + (o.suite || '');
    var equip = l.equipements.slice(0, 4).map(function (e) {
      var q = D.equipements[e];
      return '<li><i class="bi ' + q.icone + '" aria-hidden="true"></i>' + V.esc(q.label) + '</li>';
    }).join('');
    var formules = V.formulesDe(l).map(function (f) {
      return '<span class="chip ' + (f === formule ? 'chip--teal' : 'chip--line') + '">' + D.formules[f].label + '</span>';
    }).join('');
    return '' +
      '<article class="listing-card' + (o.ligne ? ' listing-card--row' : '') + '">' +
        '<div class="listing-card__media">' +
          '<img src="' + V.photo(l.photos[0], 640) + '" alt="' + V.esc(l.titre + ', ' + l.quartier) + '" width="640" height="480" loading="lazy" decoding="async">' +
          '<div class="listing-card__badges"><span class="chip">' + V.esc(l.type) + '</span>' +
            '<span class="chip"><i class="bi bi-patch-check-fill" aria-hidden="true"></i>Vérifié</span></div>' +
        '</div>' +
        '<div class="listing-card__body">' +
          '<div class="d-flex justify-content-between align-items-center gap-2">' +
            '<p class="listing-card__place"><i class="bi bi-geo-alt" aria-hidden="true"></i>' + V.esc(l.quartier + ', ' + l.ville) + '</p>' +
            '<span class="rating" aria-label="Note ' + V.note(l.note) + ' sur 5, ' + l.avis + ' avis"><i class="bi bi-star-fill" aria-hidden="true"></i>' + V.note(l.note) + ' <small>(' + l.avis + ')</small></span>' +
          '</div>' +
          '<' + h + ' class="listing-card__title"><a class="stretched-link" href="' + lien + '">' + V.esc(l.titre) + '</a></' + h + '>' +
          '<p class="listing-card__meta">' + V.pluriel(l.capacite, 'voyageur') + ' · ' + V.pluriel(l.chambres, 'chambre') + ' · ' + V.pluriel(l.lits, 'lit') + '</p>' +
          (o.ligne ? '<ul class="amenity-inline">' + equip + '</ul>' +
            '<p class="mb-0 mt-2"><span class="chip chip--success"><i class="bi bi-calendar-check" aria-hidden="true"></i>' + V.esc(l.dispo) + '</span></p>' : '') +
          '<div class="listing-card__foot">' +
            '<div class="price"><small>' + (o.formule ? 'Formule ' + D.formules[formule].label.toLowerCase() : 'À partir de') + '</small>' +
              '<strong>' + V.fcfa(l.prix[formule]) + '</strong> <span>' + V.unite(formule) + '</span></div>' +
            '<div class="formules" aria-label="Formules proposées">' + formules + '</div>' +
          '</div>' +
        '</div>' +
      '</article>';
  };

  /* ---------- Compteur − n + ---------- */
  V.compteur = function (el, o) {
    var moins = el.querySelector('[data-pas="-1"]'), plus = el.querySelector('[data-pas="1"]'), sortie = el.querySelector('output');
    var api = {
      valeur: o.valeur, min: o.min, max: o.max,
      regler: function (v, silencieux) {
        api.valeur = Math.max(api.min, Math.min(api.max, v));
        sortie.textContent = o.format ? o.format(api.valeur) : api.valeur;
        moins.disabled = api.valeur <= api.min;
        plus.disabled = api.valeur >= api.max;
        if (!silencieux && o.auChangement) { o.auChangement(api.valeur); }
      },
      bornes: function (min, max) { api.min = min; api.max = max; api.regler(api.valeur, true); }
    };
    moins.addEventListener('click', function () { api.regler(api.valeur - 1); });
    plus.addEventListener('click', function () { api.regler(api.valeur + 1); });
    api.regler(o.valeur, true);
    return api;
  };

  /* ---------- Message bref ---------- */
  V.message = function (texte) {
    var zone = document.querySelector('.toast-zone');
    if (!zone) {
      zone = document.createElement('div');
      zone.className = 'toast-zone';
      zone.setAttribute('aria-live', 'polite');
      document.body.appendChild(zone);
    }
    zone.innerHTML = '<div class="toast" role="status"><div class="toast-body"><i class="bi bi-info-circle-fill" aria-hidden="true"></i><span>' + V.esc(texte) + '</span></div></div>';
    var t = zone.firstChild;
    if (window.bootstrap && window.bootstrap.Toast) { new window.bootstrap.Toast(t, { delay: 4200 }).show(); }
    else { t.classList.add('show'); setTimeout(function () { t.remove(); }, 4200); }
  };

  /* ---------- En-tête, navigation basse, pied de page ---------- */
  function marque() {
    return '<a class="vivi-brand" href="index.html" aria-label="VIVI, appartements meublés : retour à l’accueil">' +
      '<img src="assets/img/logo/vivi-symbole.svg" alt="" width="40" height="40">' +
      '<span class="vivi-brand__text"><b>VIVI</b><small>Appartements<br>meublés.</small></span></a>';
  }
  function courant(page, nom) { return page === nom ? ' aria-current="page"' : ''; }

  V.miseEnPage = function () {
    var corps = document.body, page = corps.dataset.page || '';
    var entete = document.getElementById('vivi-entete');
    if (entete) {
      if (corps.dataset.entete === 'tunnel') {
        entete.classList.add('tunnel-header');
        entete.innerHTML = '<div class="container">' + marque() +
          '<span class="secure"><i class="bi bi-shield-lock-fill" aria-hidden="true"></i>' +
          '<span class="d-sm-none">Sécurisé</span><span class="d-none d-sm-inline">Paiement sécurisé</span></span></div>';
      } else {
        entete.innerHTML = '<div class="container">' + marque() +
          '<nav aria-label="Navigation principale"><ul class="vivi-nav">' +
            '<li><a href="recherche.html"' + courant(page, 'recherche') + '>Logements</a></li>' +
            '<li><a href="index.html#comment-ca-marche">Comment ça marche</a></li>' +
            '<li><a href="index.html#services">Services</a></li>' +
            '<li><a href="index.html#proprietaires">Propriétaires</a></li>' +
          '</ul></nav>' +
          '<div class="vivi-header__actions">' +
            '<a class="btn btn-ghost btn-sm d-none d-lg-inline-flex" href="mes-reservations.html"' + courant(page, 'reservations') + '>Mes réservations</a>' +
            '<a class="btn btn-outline-primary btn-sm" href="connexion.html"><i class="bi bi-person" aria-hidden="true"></i>Connexion</a>' +
          '</div></div>';
      }
    }

    var basse = document.getElementById('vivi-nav-basse');
    if (basse) {
      corps.classList.add('has-bottomnav');
      basse.className = 'vivi-bottomnav';
      basse.setAttribute('aria-label', 'Navigation mobile');
      basse.innerHTML =
        '<a href="index.html"' + courant(page, 'accueil') + '><i class="bi bi-house-door" aria-hidden="true"></i>Accueil</a>' +
        '<a href="recherche.html"' + courant(page, 'recherche') + '><i class="bi bi-search" aria-hidden="true"></i>Rechercher</a>' +
        '<a href="mes-reservations.html"' + courant(page, 'reservations') + '><i class="bi bi-ticket-perforated" aria-hidden="true"></i>Réservations</a>' +
        '<a href="connexion.html"' + courant(page, 'connexion') + '><i class="bi bi-person" aria-hidden="true"></i>Compte</a>';
    }

    var pied = document.getElementById('vivi-pied');
    if (pied) {
      pied.innerHTML = '<div class="container">' +
        '<div class="row g-4">' +
          '<div class="col-12 col-lg-4">' +
            '<div class="vivi-footer__brand"><img src="assets/img/logo/vivi-symbole-blanc.svg" alt="" width="44" height="44">' +
              '<span class="vivi-brand__text"><b>VIVI</b><small>Appartements<br>meublés.</small></span></div>' +
            '<p class="mb-3" style="max-width:26rem">Des appartements meublés à l’heure, à la demi-journée ou à la journée, avec des services à la carte. Partout où VIVI est présent au Bénin.</p>' +
            '<div class="pay" aria-label="Moyens de paiement acceptés">' +
              '<span class="chip">MTN Mobile Money</span><span class="chip">Moov Money</span><span class="chip">Celtiis Cash</span><span class="chip">Carte bancaire</span></div>' +
          '</div>' +
          '<div class="col-6 col-lg-2 offset-lg-1"><h2>Découvrir</h2><ul>' +
            '<li><a href="recherche.html">Tous les logements</a></li>' +
            '<li><a href="index.html#comment-ca-marche">Comment ça marche</a></li>' +
            '<li><a href="index.html#services">Services à la carte</a></li>' +
            '<li><a href="index.html#quartiers">Villes et quartiers</a></li></ul></div>' +
          '<div class="col-6 col-lg-2"><h2>Propriétaires</h2><ul>' +
            '<li><a href="index.html#proprietaires">Publier un logement</a></li>' +
            '<li><a href="pro-abonnement.html">Abonnements</a></li>' +
            '<li><a href="pro-tableau-de-bord.html">Espace propriétaire</a></li></ul></div>' +
          '<div class="col-12 col-lg-3"><h2>Aide</h2><ul>' +
            '<li><a href="#" data-bientot="Conditions d’annulation et de report">Annulation et report</a></li>' +
            '<li><a href="#" data-bientot="Conditions générales">Conditions générales</a></li>' +
            '<li><a href="#" data-bientot="Contact"><i class="bi bi-whatsapp me-2" aria-hidden="true"></i>Nous écrire sur WhatsApp</a></li></ul></div>' +
        '</div>' +
        '<div class="vivi-footer__bottom"><span>© ' + new Date().getFullYear() + ' Appartement VIVI · Nova Tech Bénin</span>' +
          '<span class="maquette-flag">Maquette : logements, prix et avis fictifs</span></div>' +
      '</div>';
    }

    /* Liens vers des écrans pas encore maquettés */
    document.addEventListener('click', function (e) {
      var a = e.target.closest('[data-bientot]');
      if (!a) { return; }
      e.preventDefault();
      V.message('« ' + a.dataset.bientot + ' » : écran prévu dans la prochaine étape de la maquette.');
    });
  };

  document.addEventListener('DOMContentLoaded', V.miseEnPage);
})();
