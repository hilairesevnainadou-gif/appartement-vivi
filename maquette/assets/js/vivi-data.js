/* ==========================================================================
   Données FICTIVES de la maquette (logements, prix, créneaux, services).
   À remplacer par la base de données quand la maquette passera en Laravel.
   ========================================================================== */
window.VIVI_DATA = {

  /* Commission de la plateforme, payée par le client sur chaque réservation et affichée avant le paiement.
     Taux PARAMÉTRABLE par l'administrateur ; 5 % est une valeur d'exemple. */
  tauxFraisService: 0.05,

  /* Report : impossible à moins de 3 heures du début du séjour (décision du 11/11/2026). */
  reportDelaiMinHeures: 3,

  /* Durée pendant laquelle un créneau reste bloqué le temps de payer (minutes). */
  dureeBlocageMinutes: 15,

  formules: {
    heure: { label: 'Heure', unite: 'heure', par: 'par heure' },
    demi:  { label: 'Demi-journée', unite: 'demi-journée', par: 'la demi-journée' },
    jour:  { label: 'Journée', unite: 'jour', par: 'par jour' }
  },

  equipements: {
    clim:     { label: 'Climatisation', icone: 'bi-snow' },
    wifi:     { label: 'Wi-Fi', icone: 'bi-wifi' },
    cuisine:  { label: 'Cuisine équipée', icone: 'bi-cup-hot' },
    tv:       { label: 'Télévision', icone: 'bi-tv' },
    parking:  { label: 'Parking', icone: 'bi-p-square' },
    groupe:   { label: 'Groupe électrogène', icone: 'bi-lightning-charge' },
    eau:      { label: 'Eau chaude', icone: 'bi-droplet' },
    securite: { label: 'Gardien 24 h/24', icone: 'bi-shield-check' },
    piscine:  { label: 'Piscine', icone: 'bi-water' },
    balcon:   { label: 'Balcon', icone: 'bi-door-open' }
  },

  /* Catalogue officiel VIVI : les prix sont fixés par VIVI, le propriétaire
     active ou non chaque service pour son logement. */
  services: [
    { id: 'boissons',  nom: 'Pack Boissons', desc: 'Eau, jus et sodas frais déposés avant votre arrivée.', prix: 5000, unite: 'le pack', icone: 'bi-cup-straw' },
    { id: 'menage',    nom: 'Ménage en cours de séjour', desc: 'Un passage de ménage à l’heure qui vous arrange.', prix: 3000, unite: 'le passage', icone: 'bi-stars' },
    { id: 'deco',      nom: 'Décoration romantique', desc: 'Bougies, pétales et mise en scène de la chambre.', prix: 10000, unite: 'la prestation', icone: 'bi-heart' },
    { id: 'chauffeur', nom: 'Chauffeur', desc: 'Un trajet aller ou retour dans la ville.', prix: 7000, unite: 'le trajet', icone: 'bi-car-front' },
    { id: 'petitdej',  nom: 'Petit-déjeuner', desc: 'Viennoiseries, fruits, café ou thé.', prix: 2500, unite: 'par personne', icone: 'bi-egg-fried' },
    { id: 'wifiplus',  nom: 'Wi-Fi haut débit', desc: 'Connexion renforcée pour travailler ou regarder un film.', prix: 2000, unite: 'le séjour', icone: 'bi-router' }
  ],

  /* Chaque logement a ses propres formules, tarifs et créneaux,
     définis par son propriétaire. Un prix à null = formule non proposée. */
  logements: [
    {
      id: 'studio-lagune', titre: 'Studio Lagune', type: 'Studio',
      ville: 'Cotonou', quartier: 'Fidjrossè',
      capacite: 2, chambres: 1, lits: 1, sdb: 1, note: 4.8, avis: 36,
      photos: ['studio-loft', 'chambre-grise', 'cuisine-blanche', 'salle-de-bain', 'coin-lecture'],
      prix: { heure: 3500, demi: 10000, jour: 18000 },
      creneaux: {
        heure: { debut: 8, fin: 22, minimum: 2 },
        demi: [
          { id: 'matin', label: 'Matin', de: '08:00', a: '13:00', icone: 'bi-sunrise' },
          { id: 'apres-midi', label: 'Après-midi', de: '14:00', a: '19:00', icone: 'bi-sun' },
          { id: 'soiree', label: 'Soirée', de: '20:00', a: '01:00', icone: 'bi-moon-stars', complet: true }
        ],
        jour: { arrivee: '12:00', depart: '11:00' }
      },
      equipements: ['clim', 'wifi', 'cuisine', 'tv', 'groupe', 'eau'],
      services: ['boissons', 'menage', 'deco', 'chauffeur', 'petitdej', 'wifiplus'],
      dispo: 'Disponible aujourd’hui'
    },
    {
      id: 'haie-vive', titre: 'Appartement Haie Vive', type: 'F3',
      ville: 'Cotonou', quartier: 'Haie Vive',
      capacite: 4, chambres: 2, lits: 2, sdb: 2, note: 4.9, avis: 58,
      photos: ['salon-cuir', 'chambre-capitonnee', 'cuisine-blanche', 'salle-de-bain-claire', 'salon-canape-clair'],
      prix: { heure: null, demi: 20000, jour: 35000 },
      creneaux: {
        demi: [
          { id: 'matin', label: 'Matin', de: '08:00', a: '13:00', icone: 'bi-sunrise' },
          { id: 'apres-midi', label: 'Après-midi', de: '14:00', a: '19:00', icone: 'bi-sun' }
        ],
        jour: { arrivee: '14:00', depart: '12:00' }
      },
      equipements: ['clim', 'wifi', 'cuisine', 'tv', 'parking', 'groupe', 'securite', 'balcon'],
      services: ['boissons', 'menage', 'chauffeur', 'petitdej', 'wifiplus'],
      dispo: 'Disponible dès demain'
    },
    {
      id: 'cocotiers', titre: 'Résidence Les Cocotiers', type: 'F2',
      ville: 'Cotonou', quartier: 'Cadjèhoun',
      capacite: 3, chambres: 1, lits: 2, sdb: 1, note: 4.6, avis: 21,
      photos: ['salon-classique', 'chambre-orange', 'salle-de-bain'],
      prix: { heure: 5000, demi: 14000, jour: 25000 },
      creneaux: {
        heure: { debut: 9, fin: 21, minimum: 2 },
        demi: [
          { id: 'journee', label: 'Journée', de: '09:00', a: '15:00', icone: 'bi-sun' },
          { id: 'soiree', label: 'Soirée', de: '16:00', a: '22:00', icone: 'bi-moon-stars' }
        ],
        jour: { arrivee: '13:00', depart: '11:00' }
      },
      equipements: ['clim', 'wifi', 'cuisine', 'tv', 'parking', 'eau'],
      services: ['boissons', 'menage', 'deco', 'petitdej'],
      dispo: 'Disponible aujourd’hui'
    },
    {
      id: 'studio-campus', titre: 'Studio Campus', type: 'Studio',
      ville: 'Abomey-Calavi', quartier: 'Zogbadjè',
      capacite: 2, chambres: 1, lits: 1, sdb: 1, note: 4.4, avis: 17,
      photos: ['studio-minimal', 'chambre-grise', 'salle-de-bain'],
      prix: { heure: 2500, demi: 7000, jour: 12000 },
      creneaux: {
        heure: { debut: 7, fin: 23, minimum: 2 },
        demi: [
          { id: 'matin', label: 'Matin', de: '07:00', a: '12:00', icone: 'bi-sunrise' },
          { id: 'apres-midi', label: 'Après-midi', de: '13:00', a: '18:00', icone: 'bi-sun' },
          { id: 'soiree', label: 'Soirée', de: '19:00', a: '00:00', icone: 'bi-moon-stars' }
        ],
        jour: { arrivee: '12:00', depart: '10:00' }
      },
      equipements: ['clim', 'wifi', 'tv', 'eau'],
      services: ['boissons', 'menage', 'wifiplus'],
      dispo: 'Disponible aujourd’hui'
    },
    {
      id: 'ouidah-plage', titre: 'Villa-appartement Ouidah Plage', type: 'F4',
      ville: 'Ouidah', quartier: 'Djègbadji',
      capacite: 6, chambres: 3, lits: 4, sdb: 2, note: 4.9, avis: 12,
      photos: ['villa-piscine', 'salon-baies', 'chambre-capitonnee', 'cuisine-blanche', 'salle-de-bain-claire'],
      prix: { heure: null, demi: null, jour: 60000 },
      creneaux: { jour: { arrivee: '15:00', depart: '11:00' } },
      equipements: ['clim', 'wifi', 'cuisine', 'tv', 'parking', 'groupe', 'piscine', 'securite'],
      services: ['boissons', 'menage', 'deco', 'chauffeur', 'petitdej'],
      dispo: 'Disponible ce week-end'
    },
    {
      id: 'akpakpa-centre', titre: 'F2 Akpakpa Centre', type: 'F2',
      ville: 'Cotonou', quartier: 'Akpakpa',
      capacite: 3, chambres: 1, lits: 2, sdb: 1, note: 4.5, avis: 29,
      photos: ['sejour-rouge', 'chambre-orange', 'cuisine-blanche'],
      prix: { heure: 3000, demi: 9000, jour: 16000 },
      creneaux: {
        heure: { debut: 8, fin: 22, minimum: 3 },
        demi: [
          { id: 'matin', label: 'Matin', de: '08:00', a: '13:00', icone: 'bi-sunrise' },
          { id: 'apres-midi', label: 'Après-midi', de: '14:00', a: '19:00', icone: 'bi-sun' }
        ],
        jour: { arrivee: '12:00', depart: '11:00' }
      },
      equipements: ['clim', 'wifi', 'cuisine', 'tv', 'groupe'],
      services: ['boissons', 'menage', 'chauffeur'],
      dispo: 'Disponible aujourd’hui'
    },
    {
      id: 'ganhi', titre: 'Chambre meublée Ganhi', type: 'Chambre',
      ville: 'Cotonou', quartier: 'Ganhi',
      capacite: 2, chambres: 1, lits: 1, sdb: 1, note: 4.3, avis: 44,
      photos: ['chambre-grise', 'coin-lecture', 'salle-de-bain'],
      prix: { heure: 2000, demi: 6000, jour: null },
      creneaux: {
        heure: { debut: 9, fin: 20, minimum: 2 },
        demi: [
          { id: 'matin', label: 'Matin', de: '09:00', a: '14:00', icone: 'bi-sunrise' },
          { id: 'apres-midi', label: 'Après-midi', de: '15:00', a: '20:00', icone: 'bi-sun' }
        ]
      },
      equipements: ['clim', 'wifi', 'tv'],
      services: ['boissons', 'menage'],
      dispo: 'Disponible aujourd’hui'
    },
    {
      id: 'jacarandas', titre: 'Loft Les Jacarandas', type: 'F2',
      ville: 'Porto-Novo', quartier: 'Ouando',
      capacite: 4, chambres: 1, lits: 2, sdb: 1, note: 4.7, avis: 9,
      photos: ['salon-plantes', 'salon-soir', 'cuisine-blanche'],
      prix: { heure: null, demi: 12000, jour: 22000 },
      creneaux: {
        demi: [
          { id: 'matin', label: 'Matin', de: '08:00', a: '13:00', icone: 'bi-sunrise' },
          { id: 'apres-midi', label: 'Après-midi', de: '14:00', a: '19:00', icone: 'bi-sun' }
        ],
        jour: { arrivee: '14:00', depart: '11:00' }
      },
      equipements: ['clim', 'wifi', 'cuisine', 'tv', 'parking', 'balcon'],
      services: ['boissons', 'menage', 'petitdej', 'wifiplus'],
      dispo: 'Disponible dès demain'
    }
  ],

  /* ---------- Espace client : réservations d'exemple ----------
     « dans » = nombre de jours par rapport à aujourd'hui (négatif = passé).
     Règles d'annulation et de report : cahier des charges V1.0, section 6. */
  delaiReportJours: 10,
  reservations: [
    { reference: 'VIVI-4HT9QC', code: '731204', statut: 'confirmee', dans: 6, logement: 'haie-vive', formule: 'jour', jours: 2, voyageurs: 3, services: { petitdej: 3 }, moyen: 'MTN Mobile Money' },
    { reference: 'VIVI-9KD2MX', code: '205817', statut: 'confirmee', dans: 0, logement: 'cocotiers', formule: 'demi', demi: 'soiree', voyageurs: 2, services: { boissons: 1 }, moyen: 'Moov Money' },
    { reference: 'VIVI-2PW7LA', code: '664390', statut: 'terminee', dans: -12, logement: 'studio-lagune', formule: 'heure', heureDebut: 15, duree: 3, voyageurs: 2, services: {}, moyen: 'MTN Mobile Money' },
    { reference: 'VIVI-6TR3NB', code: '918452', statut: 'terminee', dans: -30, logement: 'akpakpa-centre', formule: 'jour', jours: 1, voyageurs: 2, services: { menage: 1 }, moyen: 'Carte bancaire', noteDonnee: 5 },
    { reference: 'VIVI-8QZ5VD', code: '347761', statut: 'annulee', dans: -20, logement: 'studio-campus', formule: 'heure', heureDebut: 10, duree: 2, voyageurs: 1, services: {}, moyen: 'Celtiis Cash' }
  ],

  /* ---------- Espace propriétaire : données d'exemple ---------- */
  proprietaire: {
    nom: 'Rodrigue Houngbo', prenom: 'Rodrigue', initiales: 'RH',
    logements: [
      { id: 'studio-lagune', statut: 'publie', enLigne: true },
      { id: 'cocotiers', statut: 'publie', enLigne: true },
      { id: 'ganhi', statut: 'en-validation', enLigne: false }
    ],
    abonnement: { formule: 'confort', echeanceDans: 18, moyen: 'MTN Mobile Money' },

    /* Réservations de ses logements. « etat » : attendu, en-cours (QR Code scanné), termine. */
    reservations: [
      { reference: 'VIVI-3HB8TD', code: '118204', logement: 'studio-lagune', client: 'Arnaud F.', dans: 0, formule: 'demi', demi: 'matin', voyageurs: 2, services: {}, etat: 'en-cours' },
      { reference: 'VIVI-7K2M9P', code: '482916', logement: 'studio-lagune', client: 'Mireille A.', dans: 0, formule: 'heure', heureDebut: 14, duree: 3, voyageurs: 2, services: { boissons: 1 }, etat: 'attendu' },
      { reference: 'VIVI-5LN2QA', code: '905533', logement: 'cocotiers', client: 'Prisca D.', dans: 0, formule: 'demi', demi: 'soiree', voyageurs: 2, services: { deco: 1 }, etat: 'attendu' },
      { reference: 'VIVI-1XC6RE', code: '277410', logement: 'cocotiers', client: 'Ulrich T.', dans: 1, formule: 'jour', jours: 2, voyageurs: 3, services: { petitdej: 3 }, etat: 'attendu' },
      { reference: 'VIVI-8MV4ZS', code: '640918', logement: 'studio-lagune', client: 'Fifamè H.', dans: 2, formule: 'heure', heureDebut: 10, duree: 4, voyageurs: 1, services: {}, etat: 'attendu' },
      { reference: 'VIVI-4RJ8BC', code: '139682', logement: 'cocotiers', client: 'Serge B.', dans: 5, formule: 'heure', heureDebut: 11, duree: 3, voyageurs: 2, services: { boissons: 2 }, etat: 'attendu' },
      { reference: 'VIVI-6PA3YN', code: '772045', logement: 'studio-lagune', client: 'Chantal M.', dans: 6, formule: 'jour', jours: 3, voyageurs: 2, services: {}, etat: 'attendu' },
      { reference: 'VIVI-2GD9WK', code: '351776', logement: 'studio-lagune', client: 'Géraud K.', dans: -1, formule: 'jour', jours: 1, voyageurs: 2, services: { menage: 1 }, etat: 'termine' }
    ],
    blocages: [
      { logement: 'studio-lagune', dans: 4, motif: 'Travaux de peinture' }
    ],

    /* Chiffres d'exemple du tableau de bord */
    revenusMois: 412500, revenusMoisPrecedent: 366000,
    occupation: 64, occupationPrecedente: 58,
    revenus7j: [18000, 31500, 10500, 42000, 27000, 49500, 35500],   /* le dernier = aujourd'hui */
    /* Chaque séjour démarré (QR Code scanné) s'ajoute au solde. Le propriétaire demande un retrait ;
       la caissière ou l'administrateur le valide et indique le mode de règlement. */
    solde: 46500,
    retraits: [
      { reference: 'RET-0013', dans: 0, montant: 40000, etat: 'en-attente' },
      { reference: 'RET-0012', dans: -6, montant: 120000, etat: 'paye', mode: 'MTN Mobile Money' },
      { reference: 'RET-0011', dans: -20, montant: 95000, etat: 'paye', mode: 'Virement bancaire' }
    ]
  },

  /* Formules d'abonnement d'EXEMPLE : noms, prix et contenu sont paramétrés par l'administrateur. */
  abonnements: [
    { id: 'essentiel', nom: 'Essentiel', prix: 5000, logements: 1, points: ['1 logement publié', 'Calendrier et créneaux', 'Scan du QR Code'] },
    { id: 'confort', nom: 'Confort', prix: 12000, logements: 3, points: ['Jusqu’à 3 logements publiés', 'Calendrier et créneaux', 'Scan du QR Code', '1 gestionnaire délégué'] },
    { id: 'premium', nom: 'Premium', prix: 25000, logements: 10, points: ['Jusqu’à 10 logements publiés', 'Calendrier et créneaux', 'Scan du QR Code', 'Gestionnaires illimités', 'Mise en avant dans la recherche'] }
  ]
};
