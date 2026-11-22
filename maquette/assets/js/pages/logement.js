/* Fiche logement : contenu propre au logement + bloc de réservation */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var D = window.VIVI_DATA, V = window.VIVI;
  var $ = function (id) { return document.getElementById(id); };

  var l = V.logement(V.qs('id'));
  var c = l.creneaux;

  /* Réservation : on reprend celle en cours si elle concerne ce logement */
  var r = V.lireReservation();
  if (!r || r.logement !== l.id) { r = V.reservationParDefaut(l.id); }
  var qf = V.qs('formule'), qd = V.qs('date'), qv = parseInt(V.qs('voyageurs'), 10);
  if (qf && l.prix[qf] != null) { r.formule = qf; }
  if (V.dateValide(qd)) { r.date = qd; }
  if (qv) { r.voyageurs = qv; }
  V.completerReservation(r);

  /* ---------- Contenu du logement ---------- */
  document.title = l.titre + ' · ' + l.quartier + ', ' + l.ville + ' · Appartement VIVI';
  var textes = { titre: l.titre, lieu: l.quartier + ', ' + l.ville, note: V.note(l.note), avis: l.avis };
  [].forEach.call(document.querySelectorAll('[data-l]'), function (el) { el.textContent = textes[el.dataset.l]; });
  $('fil-ville').textContent = l.ville;
  $('fil-ville').href = 'recherche.html?ville=' + encodeURIComponent(l.ville);

  var photos = l.photos.slice(0, 5), galerie = $('galerie');
  galerie.dataset.count = photos.length;
  galerie.innerHTML = photos.map(function (p, i) {
    return '<div class="gallery__item"><img src="' + V.photo(p, i === 0 ? 1280 : 640) + '" alt="' + V.esc(l.titre) + ', photo ' + (i + 1) + ' sur ' + photos.length + '"' +
      ' width="' + (i === 0 ? 1280 : 640) + '" height="' + (i === 0 ? 960 : 480) + '"' + (i === 0 ? ' fetchpriority="high"' : ' loading="lazy"') + ' decoding="async"></div>';
  }).join('');

  $('faits').innerHTML =
    '<li><i class="bi bi-house-door" aria-hidden="true"></i>' + V.esc(l.type) + '</li>' +
    '<li><i class="bi bi-people" aria-hidden="true"></i>' + V.pluriel(l.capacite, 'voyageur') + '</li>' +
    '<li><i class="bi bi-door-closed" aria-hidden="true"></i>' + V.pluriel(l.chambres, 'chambre') + '</li>' +
    '<li><i class="bi bi-moon" aria-hidden="true"></i>' + V.pluriel(l.lits, 'lit') + '</li>' +
    '<li><i class="bi bi-droplet" aria-hidden="true"></i>' + V.pluriel(l.sdb, 'salle de bain', 'salles de bain') + '</li>';

  var details = {
    heure: c.heure ? 'De ' + V.heure(c.heure.debut) + ' à ' + V.heure(c.heure.fin) + ' · minimum ' + c.heure.minimum + ' h' : '',
    demi: c.demi ? c.demi.map(function (x) { return x.label + ' ' + x.de + '–' + x.a; }).join(' · ') : '',
    jour: c.jour ? 'Arrivée à partir de ' + c.jour.arrivee + ' · départ avant ' + c.jour.depart + ' le lendemain' : ''
  };
  var titres = { heure: 'À l’heure', demi: 'À la demi-journée', jour: 'À la journée' };
  $('tarifs').innerHTML = ['heure', 'demi', 'jour'].map(function (f) {
    var ok = l.prix[f] != null;
    return '<div class="tarif-row' + (ok ? '' : ' is-off') + '"><h3>' + titres[f] + '</h3>' +
      '<div class="price">' + (ok ? '<strong>' + V.fcfa(l.prix[f]) + '</strong> <span>' + V.unite(f) + '</span>' : '<span>Non proposé</span>') + '</div>' +
      '<p>' + (ok ? details[f] : 'Le propriétaire ne propose pas cette formule pour ce logement.') + '</p></div>';
  }).join('');

  $('equipements').innerHTML = l.equipements.map(function (e) {
    var q = D.equipements[e];
    return '<li><i class="bi ' + q.icone + '" aria-hidden="true"></i>' + V.esc(q.label) + '</li>';
  }).join('');

  $('services-logement').innerHTML = l.services.map(function (id) {
    var s = V.service(id);
    return '<div class="col-md-6"><div class="service-tile"><span class="service-tile__icon"><i class="bi ' + s.icone + '" aria-hidden="true"></i></span>' +
      '<div><h3>' + V.esc(s.nom) + '</h3><p>' + V.esc(s.desc) + '</p><p class="tarif">' + V.fcfa(s.prix) + ' <span>' + V.esc(s.unite) + '</span></p></div></div></div>';
  }).join('');

  /* ---------- Bloc de réservation ---------- */
  var form = $('form-reservation'), date = $('w-date'), debut = $('w-debut');

  ['heure', 'demi', 'jour'].forEach(function (f) {
    var radio = $('w-' + f);
    radio.disabled = l.prix[f] == null;
    radio.checked = r.formule === f;
  });
  date.min = V.aujourdhui();
  date.value = r.date;

  if (c.heure) {
    for (var h = c.heure.debut; h <= c.heure.fin - c.heure.minimum; h++) {
      debut.add(new Option(V.heure(h), h));
    }
    debut.value = r.heureDebut;
  }
  if (c.demi) {
    $('w-creneaux').innerHTML = c.demi.map(function (x) {
      return '<div class="slot"><input type="radio" name="demi" id="cr-' + x.id + '" value="' + x.id + '"' +
        (x.complet ? ' disabled' : '') + (r.demi === x.id ? ' checked' : '') + '>' +
        '<label for="cr-' + x.id + '"><strong><i class="bi ' + x.icone + '" aria-hidden="true"></i>' + V.esc(x.label) + '</strong>' +
        '<span>' + (x.complet ? 'Complet ce jour-là' : x.de + ' – ' + x.a) + '</span></label></div>';
    }).join('');
  }

  var cDuree = V.compteur($('c-duree'), {
    valeur: r.duree || 1, min: c.heure ? c.heure.minimum : 1, max: c.heure ? c.heure.fin - r.heureDebut : 1,
    format: function (n) { return n + ' h'; },
    auChangement: function (n) { r.duree = n; rafraichir(); }
  });
  V.compteur($('c-jours'), {
    valeur: r.jours, min: 1, max: 14,
    auChangement: function (n) { r.jours = n; rafraichir(); }
  });
  V.compteur($('c-voyageurs'), {
    valeur: r.voyageurs, min: 1, max: l.capacite,
    auChangement: function (n) { r.voyageurs = n; }
  });

  function rafraichir() {
    [].forEach.call(form.querySelectorAll('[data-formule]'), function (bloc) {
      bloc.hidden = bloc.dataset.formule !== r.formule;
    });
    var k = V.calculer({ logement: r.logement, formule: r.formule, date: r.date, heureDebut: r.heureDebut, duree: r.duree, demi: r.demi, jours: r.jours, services: {} });

    $('w-prix').textContent = V.fcfa(l.prix[r.formule]);
    $('w-unite').textContent = D.formules[r.formule].par;
    $('bb-prix').textContent = V.fcfa(l.prix[r.formule]);
    $('bb-unite').textContent = V.unite(r.formule);

    if (r.formule === 'heure') {
      $('aide-heure').textContent = 'Départ à ' + V.heure(r.heureDebut + r.duree) + ' · minimum ' + c.heure.minimum + ' h pour ce logement.';
    }
    if (r.formule === 'jour') {
      $('aide-jour').textContent = 'Arrivée à partir de ' + c.jour.arrivee + ' · départ avant ' + c.jour.depart + ' le ' + V.dateCourte(V.ajouterJours(r.date, r.jours));
    }
    $('w-lignes').innerHTML =
      '<div><dt>' + V.esc(k.detail) + '</dt><dd>' + V.fcfa(k.location) + '</dd></div>' +
      '<div><dt>Commission VIVI</dt><dd>' + V.fcfa(k.frais) + '</dd></div>' +
      '<div class="total"><dt>Total</dt><dd>' + V.fcfa(k.total) + '</dd></div>';
  }

  form.addEventListener('change', function (e) {
    var t = e.target;
    if (t.name === 'formule') { r.formule = t.value; }
    if (t.name === 'demi') { r.demi = t.value; }
    if (t === date && V.dateValide(date.value)) { r.date = date.value; }
    if (t === debut) {
      r.heureDebut = +debut.value;
      r.duree = Math.min(r.duree, c.heure.fin - r.heureDebut);
      cDuree.bornes(c.heure.minimum, c.heure.fin - r.heureDebut);
      cDuree.regler(r.duree, true);
    }
    rafraichir();
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    V.ecrireReservation(r);
    window.location.href = 'reservation.html';
  });

  /* La barre basse disparaît quand le bloc de réservation est à l'écran */
  var barre = $('barre-reserver');
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (entrees) {
      barre.classList.toggle('d-none', entrees[0].isIntersecting);
    }, { threshold: 0.15 }).observe($('reserver'));
  }

  rafraichir();
});
