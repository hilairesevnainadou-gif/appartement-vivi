/* Espace propriétaire : fiche d'un logement (informations, tarifs et créneaux, services, photos) */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var D = window.VIVI_DATA, V = window.VIVI, P = window.VIVI_PRO;
  var $ = function (id) { return document.getElementById(id); };

  var nouveau = V.qs('nouveau') === '1';
  var mien = P.logements().filter(function (x) { return x.id === V.qs('id'); })[0] || P.logements()[0];
  var l = nouveau
    ? { titre: '', type: 'Studio', ville: 'Cotonou', quartier: '', capacite: 2, chambres: 1, lits: 1, sdb: 1, prix: { heure: null, demi: null, jour: null }, creneaux: {}, equipements: [], services: [], photos: [], statut: 'brouillon' }
    : mien;
  var c = l.creneaux;

  /* ---------- En-tête ---------- */
  document.title = (nouveau ? 'Nouveau logement' : l.titre) + ' · Espace propriétaire · Appartement VIVI';
  $('ed-titre').textContent = nouveau ? 'Nouveau logement' : l.titre;
  $('ed-sous').textContent = nouveau ? 'Il sera vérifié par VIVI avant d’être publié.' : l.quartier + ', ' + l.ville;
  $('ed-statut').innerHTML = l.statut === 'publie'
    ? '<span class="chip chip--success"><i class="bi bi-check-circle" aria-hidden="true"></i>Publié</span>'
    : l.statut === 'en-validation'
      ? '<span class="chip chip--orange"><i class="bi bi-hourglass-split" aria-hidden="true"></i>En attente de validation</span>'
      : '<span class="chip chip--line"><i class="bi bi-pencil" aria-hidden="true"></i>Brouillon</span>';
  if (nouveau || l.statut !== 'publie') { $('ed-annonce').remove(); } else { $('ed-annonce').href = 'logement.html?id=' + encodeURIComponent(l.id); }

  /* ---------- Informations ---------- */
  $('i-titre').value = l.titre;
  $('i-type').value = l.type;
  $('i-ville').value = l.ville;
  $('i-quartier').value = l.quartier;
  $('i-capacite').value = l.capacite;
  $('i-chambres').value = l.chambres;
  $('i-lits').value = l.lits;
  $('i-sdb').value = l.sdb;
  $('i-description').value = nouveau ? '' : 'Un logement meublé, climatisé et prêt à vivre : literie faite, linge de maison fourni, cuisine équipée.';
  $('i-equipements').innerHTML = Object.keys(D.equipements).map(function (id) {
    var q = D.equipements[id];
    return '<div class="col-6 col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" id="eq-' + id + '"' + (l.equipements.indexOf(id) >= 0 ? ' checked' : '') + '>' +
      '<label class="form-check-label" for="eq-' + id + '">' + V.esc(q.label) + '</label></div></div>';
  }).join('');

  /* ---------- Tarifs et créneaux ---------- */
  function heures(select, choisie, de, a) {
    for (var h = de; h <= a; h++) { select.add(new Option(V.heure(h), V.heure(h))); }
    select.value = choisie;
  }
  function activer(formule, oui) {
    document.querySelector('.formule-card[data-formule="' + formule + '"]').classList.toggle('is-off', !oui);
  }

  heures($('fh-de'), V.heure(c.heure ? c.heure.debut : 8), 5, 14);
  heures($('fh-a'), V.heure(c.heure ? c.heure.fin : 22), 15, 24);
  $('fh-min').value = c.heure ? c.heure.minimum : 2;
  $('fh-prix').value = l.prix.heure || '';
  $('fh-actif').checked = l.prix.heure != null;

  $('fd-prix').value = l.prix.demi || '';
  $('fd-actif').checked = l.prix.demi != null;

  heures($('fj-arrivee'), c.jour ? c.jour.arrivee : '12:00', 8, 20);
  heures($('fj-depart'), c.jour ? c.jour.depart : '11:00', 6, 14);
  $('fj-prix').value = l.prix.jour || '';
  $('fj-actif').checked = l.prix.jour != null;

  ['heure', 'demi', 'jour'].forEach(function (f) { activer(f, l.prix[f] != null); });

  /* Créneaux de demi-journée : liste libre, propre à ce logement */
  var liste = $('fd-liste'), compteur = 0;
  function ajouterCreneau(x) {
    var n = compteur++;
    var ligne = document.createElement('div');
    ligne.className = 'creneau-row';
    ligne.innerHTML =
      '<div class="creneau-row__champs">' +
        '<div><label class="form-label" for="cn-' + n + '">Nom du créneau</label><input class="form-control" type="text" id="cn-' + n + '" value="' + V.esc(x.label) + '"></div>' +
        '<div><label class="form-label" for="cd-' + n + '">De</label><select class="form-select" id="cd-' + n + '"></select></div>' +
        '<div><label class="form-label" for="ca-' + n + '">À</label><select class="form-select" id="ca-' + n + '"></select></div>' +
      '</div>' +
      '<button type="button" class="btn-icon" aria-label="Supprimer ce créneau"><i class="bi bi-trash" aria-hidden="true"></i></button>';
    liste.appendChild(ligne);
    heures(ligne.querySelector('#cd-' + n), x.de, 5, 23);
    heures(ligne.querySelector('#ca-' + n), x.a === '00:00' ? '00:00' : x.a, 6, 26);
    verrouiller();
    return ligne;
  }
  function verrouiller() {
    var lignes = liste.querySelectorAll('.creneau-row');
    [].forEach.call(lignes, function (x) { x.querySelector('.btn-icon').disabled = lignes.length <= 1; });
    $('fd-ajouter').disabled = lignes.length >= 4;
  }
  (c.demi || [{ label: 'Matin', de: '08:00', a: '13:00' }, { label: 'Après-midi', de: '14:00', a: '19:00' }]).forEach(ajouterCreneau);
  $('fd-ajouter').addEventListener('click', function () {
    var ligne = ajouterCreneau({ label: 'Soirée', de: '20:00', a: '00:00' });
    ligne.querySelector('input').focus();
    modifie();
  });
  liste.addEventListener('click', function (e) {
    var bouton = e.target.closest('.btn-icon');
    if (!bouton || bouton.disabled) { return; }
    bouton.closest('.creneau-row').remove();
    verrouiller();
    modifie();
  });

  /* ---------- Services du catalogue VIVI ---------- */
  $('s-liste').innerHTML = D.services.map(function (s) {
    var actif = l.services.indexOf(s.id) >= 0;
    return '<div class="service-row' + (actif ? ' is-on' : '') + '" data-id="' + s.id + '">' +
      '<span class="service-tile__icon"><i class="bi ' + s.icone + '" aria-hidden="true"></i></span>' +
      '<div><h3 id="sv-' + s.id + '">' + V.esc(s.nom) + '</h3><p>' + V.esc(s.desc) + '</p></div>' +
      '<div class="service-row__action"><span class="fw-bold text-ink tnum">' + V.fcfa(s.prix) + ' <span class="fw-normal text-soft small">' + V.esc(s.unite) + '</span></span>' +
        '<div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" aria-labelledby="sv-' + s.id + '"' + (actif ? ' checked' : '') + '></div></div></div>';
  }).join('');
  $('s-liste').addEventListener('change', function (e) {
    e.target.closest('.service-row').classList.toggle('is-on', e.target.checked);
  });

  /* ---------- Photos ---------- */
  $('ph-grille').innerHTML = l.photos.map(function (p, i) {
    return '<figure><img src="' + V.photo(p, 640) + '" alt="Photo ' + (i + 1) + '" width="640" height="480" loading="lazy">' +
      (i === 0 ? '<figcaption><span class="chip chip--dark">Photo principale</span></figcaption>' : '') + '</figure>';
  }).join('') + '<button type="button" class="ajout" data-bientot="Ajout de photos"><i class="bi bi-camera" style="font-size:1.4rem" aria-hidden="true"></i>Ajouter des photos</button>';

  /* ---------- Enregistrement ---------- */
  var form = $('form-logement');
  function modifie() { $('ed-etat').textContent = 'Modifications non enregistrées.'; }
  form.addEventListener('input', modifie);
  form.addEventListener('change', function (e) {
    var carte = e.target.closest('.formule-card__head');
    if (carte) { activer(carte.parentNode.dataset.formule, e.target.checked); }
    modifie();
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var onglet = null, premier = null;
    var titre = $('i-titre'), okTitre = titre.value.trim().length >= 3;
    titre.classList.toggle('is-invalid', !okTitre);
    if (!okTitre) { onglet = 'o-infos'; premier = titre; }

    var actives = 0;
    [['fh', 'heure'], ['fd', 'demi'], ['fj', 'jour']].forEach(function (x) {
      var prix = $(x[0] + '-prix'), actif = $(x[0] + '-actif').checked, ok = !actif || +prix.value > 0;
      prix.classList.toggle('is-invalid', !ok);
      if (actif) { actives++; }
      if (!ok && !onglet) { onglet = 'o-tarifs'; premier = prix; }
    });
    if (!onglet && !actives) {
      window.bootstrap.Tab.getOrCreateInstance($('o-tarifs')).show();
      V.message('Activez au moins une formule : heure, demi-journée ou journée.');
      return;
    }
    if (onglet) {
      window.bootstrap.Tab.getOrCreateInstance($(onglet)).show();
      setTimeout(function () { premier.focus(); }, 200);
      return;
    }
    $('ed-etat').textContent = 'Enregistré à ' + new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' }).format(new Date()) + '.';
    V.message(nouveau
      ? 'Logement envoyé à VIVI pour validation. Maquette : rien n’est conservé.'
      : 'Modifications enregistrées. Maquette : rien n’est conservé.');
  });
});
