// Administration › Carte et progression : logements et salles inscrits sur la carte, filtres par statut,
// curseur des mois (le logement apparaît le mois de son inscription) et lecture animée de la progression.
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

function initialiser() {
    const element = document.getElementById('vivi-carte-admin');
    if (!element || element.dataset.pret) return;
    element.dataset.pret = '1';

    const points = JSON.parse(element.dataset.points || '[]');
    const mois = JSON.parse(element.dataset.mois || '[]');
    const carte = L.map(element, { scrollWheelZoom: true }).setView([6.42, 2.35], 10);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>',
    }).addTo(carte);

    const echapper = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const marqueurs = points.map((p) => {
        const m = L.circleMarker([p.lat, p.lng], {
            radius: p.unites > 1 ? 10 : 8, color: p.salle ? '#f4ad4c' : '#fff', weight: p.salle ? 4 : 2, fillColor: p.couleur, fillOpacity: p.en_ligne ? .95 : .5,
        }).bindPopup(
            '<div class="vivi-popup"><strong>' + echapper(p.titre) + '</strong><span>' + echapper(p.type || '') + '</span><span>' + echapper(p.lieu) + '</span>'
            + '<span>' + echapper(p.libelle) + (p.en_ligne ? '' : ' · hors ligne') + (p.unites > 1 ? ' · ' + p.unites + ' chambres' : '') + '</span>'
            + '<span>' + echapper(p.proprietaire || '') + ' · ' + p.sejours + ' séjour(s)</span>'
            + '<a href="' + echapper(p.url) + '">Ouvrir la fiche</a></div>',
        );
        return { p, m };
    });

    const statutsActifs = () => new Set([...document.querySelectorAll('[data-filtre-statut]:checked')].map((c) => c.value));
    const curseur = document.getElementById('progression-mois');
    const libelle = document.getElementById('progression-libelle');
    const total = document.getElementById('progression-total');

    function afficher() {
        const limite = mois[+curseur.value]?.cle ?? '9999-99';
        const statuts = statutsActifs();
        let visibles = 0;
        marqueurs.forEach(({ p, m }) => {
            const montrer = p.mois <= limite && statuts.has(p.statut);
            if (montrer) { visibles++; if (!carte.hasLayer(m)) m.addTo(carte); } else if (carte.hasLayer(m)) carte.removeLayer(m);
        });
        const mo = mois[+curseur.value];
        libelle.textContent = mo?.libelle ?? '';
        total.textContent = (mo ? mo.total : points.length) + ' ' + (element.dataset.mot || 'logements') + ' ' + (element.dataset.inscrits || 'inscrits') + ' · ' + visibles + ' sur la carte';
    }

    // Cadre la carte sur les logements
    if (points.length) carte.fitBounds(L.latLngBounds(points.map((p) => [p.lat, p.lng])).pad(0.2), { maxZoom: 13 });

    curseur.addEventListener('input', afficher);
    document.querySelectorAll('[data-filtre-statut]').forEach((c) => c.addEventListener('change', afficher));

    // Lecture animée : un mois toutes les 0,8 s, du premier au dernier
    const bouton = document.getElementById('progression-lecture');
    let minuterie = null;
    const icones = (lecture) => {
        bouton.querySelector('[data-icone-lecture]').style.display = lecture ? 'none' : '';
        bouton.querySelector('[data-icone-pause]').style.display = lecture ? '' : 'none';
        bouton.setAttribute('aria-label', lecture ? 'Mettre en pause' : 'Lire la progression');
    };
    const arreter = () => { clearInterval(minuterie); minuterie = null; icones(false); };
    bouton.addEventListener('click', () => {
        if (minuterie) { arreter(); return; }
        if (+curseur.value >= +curseur.max) curseur.value = 0;
        icones(true);
        afficher();
        minuterie = setInterval(() => {
            if (+curseur.value >= +curseur.max) { arreter(); return; }
            curseur.value = +curseur.value + 1;
            afficher();
        }, 800);
    });

    afficher();
    setTimeout(() => carte.invalidateSize(), 200);
}

// Filament charge les pages sans rechargement complet (navigation SPA possible) : on initialise à chaque affichage
document.addEventListener('DOMContentLoaded', initialiser);
document.addEventListener('livewire:navigated', initialiser);
initialiser();
