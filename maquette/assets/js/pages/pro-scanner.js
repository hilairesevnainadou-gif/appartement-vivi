/* Espace propriétaire : scan du QR Code à l'arrivée du client */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var D = window.VIVI_DATA, V = window.VIVI, P = window.VIVI_PRO;
  var $ = function (id) { return document.getElementById(id); };
  var aujourdhui = V.aujourdhui();
  var code = $('sc-code'), resultat = $('sc-resultat');

  /* ---------- Clients attendus ---------- */
  function listerAttendus() {
    var attendus = P.reservations().filter(function (r) { return r.date === aujourdhui && r.etat === 'attendu'; });
    $('sc-attendus').innerHTML = attendus.length ? attendus.map(function (r) {
      var h = P.horaire(r), cible = r.reference === V.qs('ref');
      return '<li><div class="quand">' + h.debut + '<small>' + h.duree + '</small></div>' +
        '<div class="qui"><strong>' + V.esc(r.client) + (cible ? ' <span class="chip chip--teal ms-1">Sélectionné</span>' : '') + '</strong>' +
          '<span>' + V.esc(V.logement(r.logement).titre) + '</span></div>' +
        '<div class="action"><button class="btn btn-ghost btn-sm tnum" type="button" data-code="' + r.code + '" aria-label="Saisir le code ' + r.code.split('').join(' ') + ' de ' + V.esc(r.client) + '">' + r.code + '</button></div></li>';
    }).join('') : '<li style="display:block" class="text-soft">Plus aucun client attendu aujourd’hui.</li>';
  }

  /* ---------- Vérification du code ---------- */
  function fiche(r) {
    var l = V.logement(r.logement), k = V.calculer(r), services = P.servicesDe(r);
    return '<dl class="detail-list mt-3">' +
      '<div><dt>Client</dt><dd>' + V.esc(r.client) + ' · ' + V.pluriel(r.voyageurs, 'voyageur') + '</dd></div>' +
      '<div><dt>Logement</dt><dd>' + V.esc(l.titre) + '</dd></div>' +
      '<div><dt>Créneau</dt><dd>' + V.esc(k.creneau) + '</dd></div>' +
      '<div><dt>À préparer</dt><dd>' + (services.length ? V.esc(services.join(', ')) : 'Aucun service') + '</dd></div>' +
      '<div><dt>Référence</dt><dd class="tnum">' + V.esc(r.reference) + '</dd></div></dl>';
  }
  function erreur(titre, texte) {
    resultat.innerHTML = '<section class="scan-result scan-result--erreur mb-3" role="alert">' +
      '<h2 class="h5"><i class="bi bi-exclamation-octagon me-2" style="color:var(--vivi-danger)" aria-hidden="true"></i>' + titre + '</h2>' +
      '<p class="mb-0">' + texte + '</p></section>';
  }

  function verifier(saisi) {
    var r = P.reservations().filter(function (x) { return x.code === saisi; })[0];
    if (!r) { erreur('Code inconnu', 'Ce code ne correspond à aucune réservation de vos logements. Vérifiez les 6 chiffres avec le client.'); return; }
    if (r.etat === 'en-cours') { erreur('QR Code déjà utilisé', 'Le séjour de ' + V.esc(r.client) + ' a déjà démarré. Un QR Code ne sert qu’une fois.'); return; }
    if (r.etat === 'termine') { erreur('Séjour terminé', 'Cette réservation est terminée depuis le ' + V.dateLongue(r.date) + '.'); return; }
    if (r.date !== aujourdhui) { erreur('Réservation prévue un autre jour', 'Ce client est attendu le ' + V.dateLongue(r.date) + ', pas aujourd’hui.'); return; }

    resultat.innerHTML = '<section class="scan-result mb-3" aria-labelledby="t-trouve">' +
      '<h2 class="h5" id="t-trouve"><i class="bi bi-check-circle-fill me-2" style="color:var(--vivi-success)" aria-hidden="true"></i>Réservation valide</h2>' +
      fiche(r) +
      '<button class="btn btn-cta btn-lg w-100 mt-3" type="button" id="sc-demarrer"><i class="bi bi-door-open" aria-hidden="true"></i>Démarrer le séjour</button>' +
      '<p class="form-text text-center mb-0 mt-2">Un QR Code ne sert qu’une fois : le séjour démarre dès que vous validez.</p></section>';

    $('sc-demarrer').addEventListener('click', function () {
      P.marquerScan(r.reference);
      var heure = new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' }).format(new Date());
      resultat.innerHTML = '<section class="panel done mb-3" role="status">' +
        '<div class="check-badge"><i class="bi bi-check-lg" aria-hidden="true"></i></div>' +
        '<h2 class="h3">Séjour démarré à ' + heure + '</h2>' +
        '<p class="mb-1">' + V.esc(r.client) + ' · ' + V.esc(V.logement(r.logement).titre) + '</p>' +
        '<p class="text-soft"><strong class="text-ink">' + V.fcfa(P.revenu(r)) + '</strong> s’ajoutent à votre solde.</p>' +
        '<div class="d-flex flex-wrap justify-content-center gap-2">' +
          '<button class="btn btn-primary" type="button" id="sc-autre"><i class="bi bi-qr-code-scan" aria-hidden="true"></i>Scanner un autre QR Code</button>' +
          '<a class="btn btn-ghost" href="pro-tableau-de-bord.html">Tableau de bord</a></div></section>';
      listerAttendus();
      $('sc-autre').addEventListener('click', function () { resultat.innerHTML = ''; code.value = ''; code.focus(); });
    });
    resultat.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }

  code.addEventListener('input', function () { code.value = code.value.replace(/\D/g, '').slice(0, 6); code.classList.remove('is-invalid'); });
  $('form-code').addEventListener('submit', function (e) {
    e.preventDefault();
    if (!/^\d{6}$/.test(code.value)) { code.classList.add('is-invalid'); code.focus(); return; }
    verifier(code.value);
  });
  $('sc-attendus').addEventListener('click', function (e) {
    var bouton = e.target.closest('[data-code]');
    if (!bouton) { return; }
    code.value = bouton.dataset.code;
    code.classList.remove('is-invalid');
    verifier(code.value);
  });
  $('sc-camera').addEventListener('click', function () {
    V.message('Maquette : la caméra n’est pas branchée. Saisissez le code de secours du client.');
  });

  listerAttendus();
});
