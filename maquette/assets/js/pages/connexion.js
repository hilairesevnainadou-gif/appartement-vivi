/* Connexion et création de compte par numéro de téléphone + code SMS */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var V = window.VIVI;
  var $ = function (id) { return document.getElementById(id); };

  var fTel = $('form-telephone'), fCode = $('form-code');
  var nom = $('a-nom'), tel = $('a-tel'), code = $('a-code');
  var minuterie = null;

  function inscription() { return $('mode-inscription').checked; }
  function chiffres(s) { return s.replace(/\D/g, ''); }
  function verifier(champ, ok) { champ.classList.toggle('is-invalid', !ok); return ok; }

  function reglerMode() {
    $('bloc-inscription').hidden = !inscription();
    $('titre-auth').textContent = inscription() ? 'Créer votre compte' : 'Bon retour';
    $('texte-auth').textContent = inscription()
      ? 'Votre numéro de téléphone suffit : nous vous envoyons un code par SMS.'
      : 'Entrez votre numéro : nous vous envoyons un code par SMS. Pas de mot de passe à retenir.';
  }
  fTel.addEventListener('change', function (e) { if (e.target.name === 'mode') { reglerMode(); } });

  function lancerDelai() {
    var reste = 30, bouton = $('renvoyer'), texte = $('delai');
    clearInterval(minuterie);
    bouton.disabled = true;
    texte.textContent = 'dans ' + reste + ' s';
    minuterie = setInterval(function () {
      reste--;
      if (reste <= 0) { clearInterval(minuterie); bouton.disabled = false; texte.textContent = ''; }
      else { texte.textContent = 'dans ' + reste + ' s'; }
    }, 1000);
  }

  fTel.addEventListener('submit', function (e) {
    e.preventDefault();
    var okNom = !inscription() || verifier(nom, nom.value.trim().length >= 3);
    var okTel = verifier(tel, chiffres(tel.value).length === 10);
    if (!okNom) { nom.focus(); return; }
    if (!okTel) { tel.focus(); return; }
    $('rappel-tel').textContent = '+229 ' + tel.value.trim();
    fTel.hidden = true;
    fCode.hidden = false;
    code.value = '';
    code.focus();
    lancerDelai();
  });

  $('changer').addEventListener('click', function (e) {
    e.preventDefault();
    fCode.hidden = true;
    fTel.hidden = false;
    tel.focus();
  });
  $('renvoyer').addEventListener('click', function () {
    V.message('Un nouveau code vient de vous être envoyé.');
    lancerDelai();
  });
  code.addEventListener('input', function () { code.value = chiffres(code.value).slice(0, 6); });

  fCode.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!verifier(code, /^\d{6}$/.test(code.value))) { code.focus(); return; }
    var proprietaire = inscription() && $('role-proprietaire').checked;
    V.message(proprietaire
      ? 'Compte créé. Bienvenue dans votre espace propriétaire.'
      : 'Vous êtes connecté.');
    setTimeout(function () { window.location.href = proprietaire ? 'pro-tableau-de-bord.html' : 'mes-reservations.html'; }, 1800);
  });

  /* Arrivée depuis « Publier mon logement » : création de compte propriétaire présélectionnée */
  if (V.qs('mode') === 'inscription') { $('mode-inscription').checked = true; }
  if (V.qs('role') === 'proprietaire') { $('role-proprietaire').checked = true; }
  reglerMode();
});
