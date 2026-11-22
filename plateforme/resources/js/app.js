import './bootstrap';

// Composants Bootstrap (menus, panneaux latéraux, modales, onglets…)
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

/* ---------- Message bref (même rendu que la maquette) ---------- */
window.viviMessage = function (texte) {
    let zone = document.querySelector('.toast-zone');
    if (!zone) {
        zone = document.createElement('div');
        zone.className = 'toast-zone';
        zone.setAttribute('aria-live', 'polite');
        document.body.appendChild(zone);
    }
    zone.innerHTML = '<div class="toast" role="status"><div class="toast-body"><i class="bi bi-info-circle-fill" aria-hidden="true"></i><span></span></div></div>';
    zone.querySelector('span').textContent = texte;
    new bootstrap.Toast(zone.firstChild, { delay: 4200 }).show();
};

/* Liens vers des écrans des lots suivants : un message au lieu d'une page vide */
document.addEventListener('click', (e) => {
    const lien = e.target.closest('[data-bientot]');
    if (!lien) return;
    e.preventDefault();
    window.viviMessage('« ' + lien.dataset.bientot + ' » arrive dans une prochaine étape.');
});

/* ---------- État de chargement ----------
 * Tout formulaire envoyé : le bouton utilisé affiche un indicateur et se désactive (pas de double envoi).
 * Exclure un formulaire : attribut data-sans-chargement. Texte pendant l'envoi : data-chargement="Paiement…".
 * Liens qui lancent une opération longue : attribut data-chargement sur le lien.
 */
function demarrerChargement(element) {
    if (!element || element.classList.contains('is-loading')) return;
    element.classList.add('is-loading');
    element.setAttribute('aria-busy', 'true');
    element.dataset.contenuInitial = element.innerHTML;
    const texte = element.dataset.chargement;
    const roue = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>';
    if (texte) {
        element.innerHTML = roue + '<span>' + texte + '</span>';
    } else {
        element.insertAdjacentHTML('afterbegin', roue);
    }
    if (element.tagName === 'BUTTON') element.disabled = true;
}

function arreterChargement(element) {
    if (!element.classList.contains('is-loading')) return;
    element.classList.remove('is-loading');
    element.removeAttribute('aria-busy');
    if (element.dataset.contenuInitial !== undefined) element.innerHTML = element.dataset.contenuInitial;
    if (element.tagName === 'BUTTON') element.disabled = false;
}
window.viviChargement = { demarrer: demarrerChargement, arreter: arreterChargement };

document.addEventListener('submit', (e) => {
    const form = e.target;
    if (form.hasAttribute('data-sans-chargement') || form.target === '_blank') return;
    const bouton = e.submitter || form.querySelector('[type=submit]');
    // Après les autres gestionnaires (confirmation, validation) : seulement si l'envoi a bien lieu.
    // Le bouton est désactivé au tour suivant, une fois ses valeurs (formaction, name) prises en compte.
    setTimeout(() => { if (!e.defaultPrevented) demarrerChargement(bouton); }, 0);
});

document.addEventListener('click', (e) => {
    const lien = e.target.closest('a[data-chargement]');
    if (lien && !e.ctrlKey && !e.metaKey && !e.shiftKey && lien.target !== '_blank') demarrerChargement(lien);
});

// Retour arrière (page restaurée depuis le cache du navigateur) : les boutons redeviennent utilisables
window.addEventListener('pageshow', (e) => {
    if (!e.persisted) return;
    document.querySelectorAll('.is-loading').forEach(arreterChargement);
    document.getElementById('vivi-barre')?.classList.remove('is-active');
});

/* ---------- Confirmation des actions ----------
 * Sur un formulaire ou sur son bouton d'envoi :
 *   data-confirmer="Message"            texte affiché (peut citer un champ du formulaire : {montant})
 *   data-confirmer-titre="Titre"        titre de la fenêtre (défaut : « Confirmer »)
 *   data-confirmer-bouton="Retirer"     libellé du bouton de confirmation (défaut : « Confirmer »)
 *   data-confirmer-style="danger"       bouton rouge pour une action destructive (défaut : primary)
 *   data-confirmer-decocher             décoche les cases du formulaire une fois confirmé (retirer un accès)
 * L'envoi n'a lieu qu'après « Confirmer » ; l'état de chargement s'affiche ensuite.
 */
const ENTITES = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
const echapperHtml = (t) => String(t).replace(/[&<>"']/g, (c) => ENTITES[c]);

function fenetreConfirmation() {
    let el = document.getElementById('vivi-confirmation');
    if (el) return el;
    document.body.insertAdjacentHTML('beforeend', [
        '<div class="modal fade" id="vivi-confirmation" tabindex="-1" aria-labelledby="vivi-confirmation-titre" aria-hidden="true">',
        '<div class="modal-dialog modal-dialog-centered"><div class="modal-content confirmation">',
        '<div class="modal-body"><span class="confirmation__icone" aria-hidden="true"><i class="bi"></i></span>',
        '<h2 class="h5 mb-2" id="vivi-confirmation-titre"></h2><p class="text-soft mb-0" data-message></p></div>',
        '<div class="modal-footer"><button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Annuler</button>',
        '<button type="button" class="btn" data-confirmer-ok></button></div>',
        '</div></div></div>',
    ].join(''));
    return document.getElementById('vivi-confirmation');
}

// « Retirer {montant} FCFA » : remplace {champ} par la valeur du champ du formulaire (nombres avec espaces)
function texteAvecChamps(texte, form) {
    return texte.replace(/\{(\w+)\}/g, (_, nom) => {
        const champ = form.elements[nom];
        const valeur = champ ? champ.value : '';
        return /^\d+$/.test(valeur) ? new Intl.NumberFormat('fr-FR').format(+valeur).replace(/\s/g, ' ') : valeur;
    });
}

window.viviConfirmer = function ({ titre = 'Confirmer', message = '', bouton = 'Confirmer', style = 'primary' } = {}) {
    return new Promise((resoudre) => {
        const el = fenetreConfirmation();
        const danger = style === 'danger';
        el.querySelector('.confirmation__icone').className = 'confirmation__icone' + (danger ? ' is-danger' : '');
        el.querySelector('.confirmation__icone i').className = 'bi ' + (danger ? 'bi-exclamation-triangle' : 'bi-question-circle');
        el.querySelector('#vivi-confirmation-titre').textContent = titre;
        el.querySelector('[data-message]').innerHTML = echapperHtml(message).replace(/\n/g, '<br>');
        const ok = el.querySelector('[data-confirmer-ok]');
        ok.className = 'btn ' + (danger ? 'btn-danger' : 'btn-primary');
        ok.textContent = bouton;
        const modale = bootstrap.Modal.getOrCreateInstance(el);
        let reponse = false;
        ok.onclick = () => { reponse = true; modale.hide(); };
        el.addEventListener('hidden.bs.modal', () => resoudre(reponse), { once: true });
        el.addEventListener('shown.bs.modal', () => ok.focus(), { once: true });
        modale.show();
    });
};

// Phase de capture : la confirmation passe avant l'état de chargement et les autres gestionnaires
document.addEventListener('submit', (e) => {
    const form = e.target;
    const source = [e.submitter, form].find((x) => x && x.hasAttribute && x.hasAttribute('data-confirmer'));
    if (!source || form.dataset.confirme === '1') return;
    e.preventDefault();
    e.stopImmediatePropagation();
    const submitter = e.submitter;
    window.viviConfirmer({
        titre: source.dataset.confirmerTitre || 'Confirmer',
        message: texteAvecChamps(source.dataset.confirmer, form),
        bouton: source.dataset.confirmerBouton || 'Confirmer',
        style: source.dataset.confirmerStyle || 'primary',
    }).then((ok) => {
        // Annulé : la page peut remettre le champ dans son état (ex. interrupteur « Visible dans la recherche »)
        if (!ok) { form.dispatchEvent(new CustomEvent('vivi:confirmation-annulee', { bubbles: true })); return; }
        if (source.hasAttribute('data-confirmer-decocher')) {
            form.querySelectorAll('input[type=checkbox]').forEach((c) => { c.checked = false; });
        }
        form.dataset.confirme = '1';
        form.requestSubmit(submitter && submitter.form === form ? submitter : undefined);
        delete form.dataset.confirme;
    });
}, true);

/* ---------- Barre de progression en haut de l'écran ----------
 * Visible dès qu'une page est demandée (formulaire envoyé, lien interne), jusqu'à l'affichage de la suivante.
 */
function barreChargement() {
    let barre = document.getElementById('vivi-barre');
    if (!barre) {
        barre = document.createElement('div');
        barre.id = 'vivi-barre';
        barre.setAttribute('role', 'progressbar');
        barre.setAttribute('aria-label', 'Chargement de la page');
        document.body.appendChild(barre);
    }
    barre.classList.remove('is-active');
    void barre.offsetWidth;   // relance l'animation
    barre.classList.add('is-active');
}
window.viviBarre = barreChargement;

document.addEventListener('submit', (e) => {
    const form = e.target;
    if (form.hasAttribute('data-sans-chargement') || form.target === '_blank') return;
    setTimeout(() => { if (!e.defaultPrevented) barreChargement(); }, 0);
});

document.addEventListener('click', (e) => {
    const lien = e.target.closest('a[href]');
    if (!lien || e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
    if (lien.target === '_blank' || lien.hasAttribute('download') || lien.hasAttribute('data-bs-toggle') || lien.hasAttribute('data-bientot')) return;
    const url = new URL(lien.href, location.href);
    if (url.origin !== location.origin || !url.protocol.startsWith('http')) return;
    if (url.pathname === location.pathname && url.search === location.search) return;   // ancre ou même page
    barreChargement();
});

/* ---------- « Près de moi » ----------
 * Bouton [data-pres-de-moi] : demande la position au navigateur, puis ouvre la liste des logements
 * du plus proche au plus loin. La date et les voyageurs du formulaire voisin sont gardés ; la ville est ignorée.
 * La position ne sert qu'à cette recherche : elle n'est pas enregistrée.
 */
document.addEventListener('click', (e) => {
    const bouton = e.target.closest('[data-pres-de-moi]');
    if (!bouton) return;
    if (!navigator.geolocation) {
        window.viviMessage('Votre navigateur ne permet pas de partager votre position. Choisissez une ville.');
        return;
    }
    demarrerChargement(bouton);
    navigator.geolocation.getCurrentPosition((pos) => {
        const params = new URLSearchParams();
        const form = bouton.closest('form');
        if (form) {
            new FormData(form).forEach((valeur, cle) => {
                if (!['ville', 'page', 'lat', 'lng', 'tri'].includes(cle) && valeur !== '') params.append(cle, valeur);
            });
        }
        params.set('lat', pos.coords.latitude.toFixed(5));
        params.set('lng', pos.coords.longitude.toFixed(5));
        barreChargement();
        location.href = (bouton.dataset.presDeMoi || '/logements') + '?' + params.toString();
    }, (erreur) => {
        arreterChargement(bouton);
        window.viviMessage(erreur.code === 1
            ? 'Position refusée : autorisez la localisation dans votre navigateur, ou choisissez une ville.'
            : 'Position introuvable pour le moment. Réessayez, ou choisissez une ville.');
    }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 600000 });
});
