/* Espace propriétaire : calendrier d'un logement (mois + détail de la journée) */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var D = window.VIVI_DATA, V = window.VIVI, P = window.VIVI_PRO, O = D.proprietaire;
  var $ = function (id) { return document.getElementById(id); };
  var aujourdhui = V.aujourdhui();
  var iso = function (a, m, j) { return a + '-' + (m < 9 ? '0' : '') + (m + 1) + '-' + (j < 10 ? '0' : '') + j; };

  var logements = P.logements(), choix = $('cal-logement');
  logements.forEach(function (l) { choix.add(new Option(l.titre, l.id)); });
  if (P.estMien(V.qs('id'))) { choix.value = V.qs('id'); }

  var mois = { annee: +aujourdhui.slice(0, 4), index: +aujourdhui.slice(5, 7) - 1 };
  var jourChoisi = aujourdhui;
  /* Jours bloqués par le propriétaire : ceux des données + ceux ajoutés pendant la visite */
  var blocages = O.blocages.map(function (b) { return { logement: b.logement, date: V.ajouterJours(aujourdhui, b.dans), motif: b.motif }; });

  function reservationsDu(jour) {
    return P.reservations().filter(function (r) {
      if (r.logement !== choix.value) { return false; }
      var fin = r.formule === 'jour' ? V.ajouterJours(r.date, r.jours - 1) : r.date;
      return jour >= r.date && jour <= fin;
    });
  }
  function blocageDu(jour) {
    return blocages.filter(function (b) { return b.logement === choix.value && b.date === jour; })[0];
  }
  function etatDu(jour) {
    if (blocageDu(jour)) { return 'bloque'; }
    var liste = reservationsDu(jour);
    if (!liste.length) { return 'libre'; }
    return liste.some(function (r) { return r.formule === 'jour'; }) ? 'complet' : 'partiel';
  }
  var libelles = { libre: 'libre', partiel: 'réservé en partie', complet: 'complet', bloque: 'bloqué' };

  /* ---------- Mois ---------- */
  function dessinerMois() {
    var premier = new Date(mois.annee, mois.index, 1);
    $('cal-mois').textContent = new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric' }).format(premier);
    var decalage = (premier.getDay() + 6) % 7;                       /* la semaine commence le lundi */
    var nbJours = new Date(mois.annee, mois.index + 1, 0).getDate();
    var h = ['lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.', 'dim.'].map(function (j) { return '<div class="cal__dow" aria-hidden="true">' + j + '</div>'; }).join('');
    for (var v = 0; v < decalage; v++) { h += '<span></span>'; }
    for (var j = 1; j <= nbJours; j++) {
      var d = iso(mois.annee, mois.index, j), passe = d < aujourdhui, etat = passe ? 'libre' : etatDu(d);
      h += '<button type="button" class="cal__day' + (d === aujourdhui ? ' is-today' : '') + '" data-date="' + d + '" data-etat="' + etat + '"' +
        (passe ? ' disabled' : '') + ' aria-pressed="' + (d === jourChoisi) + '" aria-label="' + V.dateLongue(d) + ', ' + libelles[etat] + '">' +
        j + '<span class="etat" aria-hidden="true"></span></button>';
    }
    $('cal-grille').innerHTML = h;
    $('cal-avant').disabled = mois.annee === +aujourdhui.slice(0, 4) && mois.index === +aujourdhui.slice(5, 7) - 1;
  }

  /* ---------- Journée ---------- */
  function dessinerJour() {
    var liste = reservationsDu(jourChoisi), blocage = blocageDu(jourChoisi), etat = etatDu(jourChoisi);
    $('cal-jour').textContent = V.dateLongue(jourChoisi);
    $('cal-etat').innerHTML = {
      libre: '<span class="chip chip--success">Libre</span>',
      partiel: '<span class="chip chip--orange">Réservé en partie</span>',
      complet: '<span class="chip chip--teal">Complet</span>',
      bloque: '<span class="chip chip--line">Bloqué</span>'
    }[etat];

    var h = liste.map(function (r) {
      var t = P.horaire(r), services = P.servicesDe(r), detail = D.formules[r.formule].label;
      if (r.formule === 'jour') {
        var rang = V.joursEntre(r.date, jourChoisi) + 1;
        detail += r.jours > 1 ? ', jour ' + rang + ' sur ' + r.jours : '';
      }
      var horaire = r.formule === 'jour'
        ? (jourChoisi === r.date ? t.debut + '<small>arrivée</small>' : 'Journée<small>sur place</small>')
        : t.debut + '<small>→ ' + t.fin + '</small>';
      return '<li class="resa"><div class="h">' + horaire + '</div>' +
        '<div><strong>' + V.esc(r.client) + ' · ' + V.pluriel(r.voyageurs, 'voyageur') + '</strong>' +
          '<span class="d">' + detail + ' · ' + V.fcfa(P.revenu(r)) + (services.length ? ' · ' + V.esc(services.join(', ')) : '') + '</span></div>' +
        '<div>' + P.pastille(r.etat) + '</div></li>';
    }).join('');

    if (blocage) {
      h += '<li class="bloque"><div class="h">Journée<small>bloquée</small></div><div><strong>Aucune réservation possible</strong><span class="d">' + V.esc(blocage.motif) + '</span></div><div></div></li>';
    } else if (etat !== 'complet') {
      h += '<li class="libre"><div class="h">Libre</div><div><strong>' + (liste.length ? 'Le reste de la journée' : 'Toute la journée') + '</strong>' +
        '<span class="d">Réservable selon les créneaux de ce logement.</span></div><div></div></li>';
    }
    $('cal-agenda').innerHTML = h;

    $('cal-action').innerHTML = blocage
      ? '<button class="btn btn-outline-primary" type="button" data-action="debloquer"><i class="bi bi-unlock" aria-hidden="true"></i>Débloquer cette journée</button>'
      : liste.length
        ? '<p class="form-text mb-0"><i class="bi bi-lock me-1" aria-hidden="true"></i>Cette journée ne peut pas être bloquée : elle a déjà des réservations.</p>'
        : '<button class="btn btn-ghost" type="button" data-action="bloquer"><i class="bi bi-lock" aria-hidden="true"></i>Bloquer cette journée</button>' +
          '<p class="form-text mb-0 mt-2">Un jour bloqué n’apparaît plus comme disponible pour les clients.</p>';
  }

  $('cal-grille').addEventListener('click', function (e) {
    var jour = e.target.closest('.cal__day');
    if (!jour || jour.disabled) { return; }
    jourChoisi = jour.dataset.date;
    dessinerMois(); dessinerJour();
    var actif = $('cal-grille').querySelector('[data-date="' + jourChoisi + '"]');
    if (actif) { actif.focus(); }
  });
  $('cal-action').addEventListener('click', function (e) {
    var bouton = e.target.closest('[data-action]');
    if (!bouton) { return; }
    if (bouton.dataset.action === 'bloquer') {
      blocages.push({ logement: choix.value, date: jourChoisi, motif: 'Bloqué par le propriétaire' });
      V.message('Journée bloquée : elle n’est plus proposée aux clients.');
    } else {
      blocages = blocages.filter(function (b) { return !(b.logement === choix.value && b.date === jourChoisi); });
      V.message('Journée débloquée : elle est de nouveau réservable.');
    }
    dessinerMois(); dessinerJour();
  });
  function changerMois(pas) {
    var d = new Date(mois.annee, mois.index + pas, 1);
    mois = { annee: d.getFullYear(), index: d.getMonth() };
    dessinerMois();
  }
  $('cal-avant').addEventListener('click', function () { changerMois(-1); });
  $('cal-apres').addEventListener('click', function () { changerMois(1); });
  choix.addEventListener('change', function () { dessinerMois(); dessinerJour(); });

  dessinerMois();
  dessinerJour();
});
