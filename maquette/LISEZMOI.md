# Maquette Appartement VIVI · phase 1, location courte durée

Maquette statique de la plateforme web : le site client, l'espace client et l'espace propriétaire. Elle sert à valider l'interface avant son intégration dans Laravel (vues Blade). Rien n'est branché sur un serveur : les données sont fictives et le paiement est simulé.

## Voir la maquette

- XAMPP démarré : <http://localhost/Appartement%20VIVI/maquette/>
- Sans XAMPP : ouvrir `index.html` dans le navigateur.

Pour la vue mobile, réduire la fenêtre ou utiliser le mode « appareil » du navigateur (F12).

## Écrans

### Site client

| Fichier | Écran |
| --- | --- |
| `index.html` | Accueil et recherche |
| `recherche.html` | Résultats et filtres |
| `logement.html?id=…` | Fiche logement et bloc de réservation |
| `reservation.html` | Services à la carte, puis paiement |
| `confirmation.html` | Billet avec QR Code et code de secours |
| `connexion.html` | Connexion et création de compte par code SMS |

### Espace client

| Fichier | Écran |
| --- | --- |
| `mes-reservations.html` | Réservations à venir, passées et annulées |
| `ma-reservation.html?ref=…` | Détail d'une réservation, QR Code, avis après le séjour |
| `reporter.html?ref=…` | Report : nouvelle date et nouveau créneau |
| `annuler.html?ref=…` | Annulation : montant remboursé, puis confirmation |

### Espace propriétaire

| Fichier | Écran |
| --- | --- |
| `pro-tableau-de-bord.html` | Indicateurs, arrivées du jour, revenus, reversements |
| `pro-logements.html` | Liste des logements et mise en pause |
| `pro-logement.html?id=…` | Informations, tarifs et créneaux, services, photos |
| `pro-calendrier.html` | Mois, détail d'une journée, blocage d'un jour |
| `pro-scanner.html` | Scan du QR Code ou saisie du code de secours |
| `pro-abonnement.html` | Formule en cours, changement de formule, paiements |

Les liens vers des écrans pas encore maquettés affichent un message « prévu dans la prochaine étape ».

## Enchaînements à essayer

- **Réserver** : accueil, recherche, fiche logement, services, paiement, confirmation.
- **Retrouver sa réservation** : après un paiement, elle apparaît en tête de « Mes réservations ».
- **Scanner côté propriétaire** : une réservation faite sur Studio Lagune ou Résidence Les Cocotiers apparaît dans le tableau de bord du propriétaire. Son code de secours est accepté sur l'écran de scan.
- **Reporter ou annuler** : depuis le détail d'une réservation. Les changements sont gardés jusqu'à la fermeture de l'onglet.

## Organisation des fichiers

| Emplacement | Contenu |
| --- | --- |
| `assets/css/vivi.css` | La charte : couleurs du logo, typographie, composants. Surcouche de Bootstrap 5.3. |
| `assets/css/vivi-pro.css` | Les composants de l'espace propriétaire : menu latéral, indicateurs, graphique, calendrier, scan. |
| `assets/js/vivi-data.js` | Toutes les données fictives : logements, tarifs, créneaux, services, réservations, propriétaire, abonnements. |
| `assets/js/vivi.js` | Fonctions communes : prix, dates, réservations, remboursement. En-tête, pied de page et navigation mobile du site y sont définis une seule fois. |
| `assets/js/vivi-pro.js` | Fonctions communes de l'espace propriétaire, dont son menu. |
| `assets/js/pages/` | Un script par écran. |
| `assets/vendor/` | Bootstrap 5.3.8, Bootstrap Icons 1.13.2, polices Outfit et DM Sans, qrcode-generator 2.0.4. Tout est local, aucun appel externe. |

Les blocs injectés par `vivi.js` et `vivi-pro.js` (en-tête, pied de page, menus) deviendront des partiels Blade.

## Ce qui est fictif ou simulé

- **Logements, prix, créneaux, avis, réservations, chiffres du tableau de bord** : inventés pour remplir les écrans.
- **Commission de la plateforme** : 5 %, valeur d'exemple (paramétrable par l'administrateur). Dans la maquette, elle se change dans `tauxFraisService` de `assets/js/vivi-data.js`.
- **Formules d'abonnement** : noms, prix et contenus d'exemple (paramétrables par l'administrateur), dans `abonnements` du même fichier.
- **Paiement** : aucun appel à FedaPay, la confirmation arrive après quelques secondes.
- **QR Code** : généré dans le navigateur. En production, il est généré et signé par le serveur.
- **Scan** : la caméra n'est pas branchée, seul le code de secours fonctionne.
- **Code SMS** : n'importe quel code à 6 chiffres est accepté.
- **Calculs** : prix et remboursement sont calculés dans le navigateur pour la démonstration. En production, ils sont faits par le serveur.

## Règles de gestion appliquées

Ces règles viennent du cahier des charges V1.0 et des décisions du 10 et du 11 novembre 2026.

| Règle | Dans la maquette | Source |
| --- | --- | --- |
| Commission de la plateforme | Payée par le client sur chaque réservation, affichée avant le paiement ; taux paramétrable | Décisions du 10 et 11/11 |
| Créneaux | Formules, horaires et tarifs propres à chaque logement | Décision de démarrage |
| Annulation, heure et demi-journée | Gratuite jusqu'à 24 h avant ; ensuite la location reste due | CDC 6.1, politique Flexible |
| Annulation, journée | Gratuite jusqu'à 5 jours avant ; ensuite la première journée et la moitié des suivantes restent dues | CDC 6.1, politique Modérée |
| Report | Gratuit, impossible à moins de 3 h de l'arrivée ; nouvelle date dans les 10 jours ; une réservation reportée n'est plus remboursable | Décision du 11/11 et CDC 6.2 |
| Solde et retraits | Le scan du QR Code ajoute la location au solde du propriétaire ; il demande un retrait, que la caissière ou l'administrateur valide en indiquant le mode de règlement | Décision du 11/11 |
| Services à la carte | Le bénéficiaire (propriétaire ou VIVI) est paramétrable ; la maquette les attribue au propriétaire | Décision du 11/11 |
| Notifications | SMS et email activables ou désactivables par l'administrateur | Décision du 11/11 |
| Abonnement | Nécessaire pour publier ; nombre de logements limité par la formule | Décision de démarrage, détail inventé |
| Temps de préparation | Durée bloquée après chaque départ, réglée par logement | Proposition de la maquette |

Le calcul du remboursement est dans `V.remboursement` (`assets/js/vivi.js`).

## Images à remplacer avant la mise en ligne

- **Photos** : illustrations provenant d'Unsplash (licence Unsplash, usage libre), à remplacer par les photos réelles des logements. Elles sont réencodées en WebP en deux tailles, 640 et 1280 px de large.
- **Logo** : nouveau logo validé le 11 novembre 2026 (piste F de `logos.html`), dans `assets/img/logo/`. Tous les fichiers sont vectoriels ou générés à partir du SVG ; aucun n'est à remplacer.

### Fichiers du logo

| Fichier | Usage |
| --- | --- |
| `vivi-symbole.svg` | Symbole seul, sur fond clair (en-tête du site) |
| `vivi-symbole-blanc.svg` | Symbole seul, sur fond sombre (pied de page, menu propriétaire) |
| `vivi-logo.svg`, `vivi-logo-blanc.svg` | Symbole et nom VIVI, en tracés (administration, documents) |
| `vivi-icone.svg`, `icone-192.png`, `icone-512.png`, `apple-touch-icon.png` | Icône d'application |
| `favicon.svg`, `favicon-32.png` | Icône d'onglet, sans fenêtres pour rester lisible à 16 px |
| `vivi-logo-1200.png` | Logo en image, pour les supports qui n'acceptent pas le SVG |

La mention « Appartements meublés. » est écrite en HTML à côté du symbole, dans la police Outfit du site. Couleurs : turquoise `#136f6e`, turquoise foncé `#0a3d3e`, orange `#f4ad4c` pour la porte.

Identifiants Unsplash des photos (`images.unsplash.com/photo-<identifiant>`) :

| Fichier | Identifiant |
| --- | --- |
| `hero` | 1493809842364-78817add7ffb |
| `cuisine-blanche` | 1484154218962-a197022b5858 |
| `salon-plantes` | 1502672260266-1c1ef2d93688 |
| `salon-canape-clair` | 1505691938895-1758d7feb511 |
| `chambre-capitonnee` | 1505693416388-ac5ce068fe85 |
| `villa-piscine` | 1512917774080-9991f1c4c750 |
| `studio-minimal` | 1513694203232-719a280e022f |
| `sejour-rouge` | 1522708323590-d24dbb6b0267 |
| `chambre-grise` | 1522771739844-6a9f6d5f14af |
| `studio-loft` | 1536376072261-38c75010e6c9 |
| `chambre-orange` | 1540518614846-7eded433c457 |
| `salle-de-bain-claire` | 1552321554-5fefe8c9ef14 |
| `salon-cuir` | 1554995207-c18c203602cb |
| `salon-classique` | 1560185127-6ed189bf02f4 |
| `salle-de-bain` | 1560448075-bb485b067938 |
| `salon-baies` | 1560448204-e02f11c3d0e2 |
| `salon-soir` | 1564078516393-cf04bd966897 |
| `coin-lecture` | 1586023492125-27b2c045efd7 |

## Écrans restant à maquetter

- **Propriétaire** : toutes les réservations, revenus et reversements, gestionnaires délégués.
- **Gestionnaire de propriétaire** et **caissière** : à définir avec le cahier des charges.
- **Pages de contenu** : conditions générales, conditions d'annulation et de report, contact.
- **Détails** : visionneuse de photos, liste complète des avis, ajout de photos, changement de moyen de paiement.
- **Administration** : réalisée avec Filament, pas de maquette HTML prévue.
