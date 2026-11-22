// Cartes (Leaflet + OpenStreetMap) :
//  - [data-carte-editeur] : formulaire du logement. « Utiliser ma position », clic ou glisser du repère ;
//    l'adresse est proposée à partir de la position (OpenStreetMap / Nominatim) et reste modifiable.
//  - [data-carte-zone] : fiche publique. Une zone d'environ 400 m autour du logement, jamais le point exact
//    (l'adresse précise n'est envoyée au client qu'après le paiement).
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Repère aux couleurs de VIVI (dessiné en CSS : pas d'image à charger)
const REPERE = L.divIcon({ className: 'vivi-repere', html: '<span></span>', iconSize: [30, 42], iconAnchor: [15, 40] });

const TUILES = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>';
// Centre des villes ouvertes, quand le logement n'a pas encore de position
const VILLES = {
    'Cotonou': [6.3654, 2.4183], 'Abomey-Calavi': [6.4485, 2.3557], 'Porto-Novo': [6.4969, 2.6283], 'Ouidah': [6.3631, 2.0851],
};

function creerCarte(element, centre, zoom) {
    const carte = L.map(element, { scrollWheelZoom: false }).setView(centre, zoom);
    L.tileLayer(TUILES, { maxZoom: 19, attribution: ATTRIBUTION }).addTo(carte);
    return carte;
}

/** Adresse lisible d'une position (OpenStreetMap). */
async function adresseDe(lat, lng) {
    const url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&zoom=18&accept-language=fr'
        + '&lat=' + lat + '&lon=' + lng;
    const reponse = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!reponse.ok) throw new Error('adresse');
    const a = (await reponse.json()).address || {};
    return {
        rue: [a.house_number, a.road || a.pedestrian || a.footway].filter(Boolean).join(' '),
        quartier: a.suburb || a.neighbourhood || a.quarter || a.city_district || a.hamlet || '',
        ville: a.city || a.town || a.village || a.municipality || a.county || '',
    };
}

function editeur(bloc) {
    const $ = (id) => document.getElementById(id);
    const lat = $('i-lat'), lng = $('i-lng'), etat = $('geo-etat');
    const champVille = $('i-ville'), champQuartier = $('i-quartier'), champAdresse = $('i-adresse');
    const connu = lat.value !== '' && lng.value !== '';
    const centre = connu ? [+lat.value, +lng.value] : (VILLES[champVille?.value] || VILLES.Cotonou);
    const carte = creerCarte(bloc.querySelector('[data-carte]'), centre, connu ? 17 : 13);
    let repere = null;
    const dire = (texte, erreur = false) => { etat.textContent = texte; etat.classList.toggle('text-danger', erreur); };

    async function placer(la, ln, { depuisGps = false, precision = null, initial = false } = {}) {
        lat.value = la.toFixed(7);
        lng.value = ln.toFixed(7);
        if (!repere) {
            repere = L.marker([la, ln], { draggable: true, icon: REPERE, keyboard: true, title: 'Position du logement' }).addTo(carte);
            repere.on('dragend', () => { const p = repere.getLatLng(); placer(p.lat, p.lng); });
        } else {
            repere.setLatLng([la, ln]);
        }
        carte.setView([la, ln], Math.max(carte.getZoom(), 17));
        if (initial) return;   // position déjà enregistrée : rien n'a changé
        lat.dispatchEvent(new Event('change', { bubbles: true }));   // « modifications en attente » du formulaire
        dire((precision ? 'Position trouvée (précision ± ' + Math.round(precision) + ' m). ' : 'Position enregistrée. ') + 'Recherche de l’adresse…');
        try {
            const adr = await adresseDe(la, ln);
            if (adr.rue && (depuisGps || !champAdresse.value)) champAdresse.value = adr.rue;
            if (adr.quartier && (depuisGps || !champQuartier.value)) champQuartier.value = adr.quartier;
            const option = [...(champVille?.options || [])].find((o) => o.value.toLowerCase() === adr.ville.toLowerCase());
            if (option) champVille.value = option.value;
            dire('Adresse proposée : ' + [adr.rue, adr.quartier, adr.ville].filter(Boolean).join(', ') + '. Vérifiez-la et complétez-la (repère, portail…).');
        } catch (e) {
            dire('Position enregistrée. L’adresse n’a pas pu être proposée : saisissez-la.');
        }
    }

    if (connu) placer(+lat.value, +lng.value, { initial: true });

    carte.on('click', (e) => placer(e.latlng.lat, e.latlng.lng));
    champVille?.addEventListener('change', () => { if (!repere && VILLES[champVille.value]) carte.setView(VILLES[champVille.value], 13); });

    $('geo-moi').addEventListener('click', () => {
        if (!navigator.geolocation) { dire('Votre navigateur ne donne pas la position : cliquez sur la carte.', true); return; }
        const bouton = $('geo-moi');
        window.viviChargement?.demarrer(bouton);
        dire('Localisation en cours… Restez dans le logement pour une adresse précise.');
        navigator.geolocation.getCurrentPosition(
            (pos) => { window.viviChargement?.arreter(bouton); placer(pos.coords.latitude, pos.coords.longitude, { depuisGps: true, precision: pos.coords.accuracy }); },
            (err) => {
                window.viviChargement?.arreter(bouton);
                dire(err.code === 1 ? 'Position refusée : autorisez la localisation, ou cliquez sur la carte.' : 'Position introuvable : réessayez ou cliquez sur la carte.', true);
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
        );
    });
    // La carte est dans un onglet : on la redimensionne quand elle devient visible
    document.addEventListener('shown.bs.tab', () => carte.invalidateSize());
}

function zone(element) {
    const centre = [+element.dataset.lat, +element.dataset.lng];
    const carte = creerCarte(element, centre, 15);
    L.circle(centre, { radius: 400, color: '#136f6e', weight: 2, fillColor: '#64b8b8', fillOpacity: .25 }).addTo(carte);
}

/**
 * Carte de positionnement réutilisable (administration Filament) : même fonctionnement que l'éditeur du
 * propriétaire, mais les valeurs passent par surPosition(lat, lng, adresse, depuisGps) au lieu de champs cachés.
 */
window.viviCarteChamp = function (element, { lat, lng, ville, bouton, etat, surPosition }) {
    if (!element || element.dataset.pret) return;
    element.dataset.pret = '1';
    const connu = lat !== null && lat !== '' && lat !== undefined && lng !== null && lng !== '' && lng !== undefined;
    const carte = creerCarte(element, connu ? [+lat, +lng] : (VILLES[ville] || VILLES.Cotonou), connu ? 17 : 13);
    let repere = null;
    const dire = (texte, erreur = false) => { if (etat) { etat.textContent = texte; etat.style.color = erreur ? '#b83a2b' : ''; } };

    function poser(la, ln) {
        if (!repere) {
            repere = L.marker([la, ln], { draggable: true, icon: REPERE, title: 'Position du logement' }).addTo(carte);
            repere.on('dragend', () => { const p = repere.getLatLng(); placer(p.lat, p.lng); });
        } else {
            repere.setLatLng([la, ln]);
        }
        carte.setView([la, ln], Math.max(carte.getZoom(), 17));
    }

    async function placer(la, ln, depuisGps = false, precision = null) {
        poser(la, ln);
        surPosition?.(la, ln, null, depuisGps);
        dire((precision ? 'Position trouvée (± ' + Math.round(precision) + ' m). ' : 'Position enregistrée. ') + 'Recherche de l’adresse…');
        try {
            const adr = await adresseDe(la, ln);
            surPosition?.(la, ln, adr, depuisGps);
            dire('Adresse proposée : ' + [adr.rue, adr.quartier, adr.ville].filter(Boolean).join(', ') + '. Vérifiez-la, puis enregistrez.');
        } catch (e) {
            dire('Position enregistrée. L’adresse n’a pas pu être proposée : saisissez-la.');
        }
    }

    if (connu) { poser(+lat, +lng); dire('Déplacez le repère si besoin.'); } else { dire('Aucune position pour le moment.'); }
    carte.on('click', (e) => placer(e.latlng.lat, e.latlng.lng));
    bouton?.addEventListener('click', () => {
        if (!navigator.geolocation) { dire('Ce navigateur ne donne pas la position : cliquez sur la carte.', true); return; }
        bouton.disabled = true;
        dire('Localisation en cours…');
        navigator.geolocation.getCurrentPosition(
            (pos) => { bouton.disabled = false; placer(pos.coords.latitude, pos.coords.longitude, true, pos.coords.accuracy); },
            (err) => { bouton.disabled = false; dire(err.code === 1 ? 'Position refusée : autorisez la localisation, ou cliquez sur la carte.' : 'Position introuvable : réessayez ou cliquez sur la carte.', true); },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
        );
    });
    setTimeout(() => carte.invalidateSize(), 250);
};
document.dispatchEvent(new Event('vivi:carte-prete'));

document.querySelectorAll('[data-carte-editeur]').forEach(editeur);
document.querySelectorAll('[data-carte-zone]').forEach(zone);
