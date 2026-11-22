# API de l'application mobile VIVI

Une seule application Android pour les **clients**, les **propriétaires** et les **gestionnaires**.
Elle utilise les mêmes règles que le site : prix, disponibilités, commission, report, annulation et scanner sont calculés par le serveur.

- **Adresse de base** : `https://{domaine}/api/v1` (en local : `http://127.0.0.1:8000/api/v1`)
- **Format** : JSON en UTF-8. Envoyer `Accept: application/json`. Pour les photos, envoyer en `multipart/form-data`.
- **Langue** : tous les messages sont en français et peuvent être affichés tels quels.
- **Montants** : entiers en FCFA (XOF), sans décimales.
- **Dates** : ISO 8601 avec fuseau (`2026-11-13T14:00:00+01:00`) ; jours au format `AAAA-MM-JJ` ; heures au format `HH:MM`.
- **Énumérations** : toujours `{ "valeur": "confirmee", "libelle": "Confirmée" }`. Le code teste `valeur` et l'écran affiche `libelle`.

## 1. Authentification

Il n'y a pas de mot de passe. Un code à 6 chiffres est envoyé, puis échangé contre un jeton. VIVI est international, et l'écran propose deux onglets :

- **Au Bénin**, `pays: "benin"` : numéro béninois (10 chiffres, commençant par 01, code par SMS) **ou** adresse email (code par email), dans un seul champ « Numéro béninois ou email » ;
- **International**, `pays: "international"` : adresse email seulement.

`pays` est facultatif ; sans lui, la règle du Bénin s'applique. Bouton : « Se connecter » ou « Créer mon compte ».

```
POST /auth/code
{ "mode": "connexion", "pays": "benin", "identifiant": "01 97 00 00 00" }
{ "mode": "inscription", "pays": "international", "identifiant": "awa@exemple.com", "nom": "Awa Dossou", "role": "client|proprietaire", "invitation": "CODE" }
→ 201 { "demande": "…48 caractères…", "canal": "sms|mail", "destination": "01 97 •• •• 00",
        "renvoi_secondes": 30, "validite_minutes": 10, "code_dev": "123456" }   // code_dev n'est rempli qu'en local

POST /auth/renvoyer   { "demande": "…" }
POST /auth/verifier   { "demande": "…", "code": "123456", "appareil": "Tecno Spark 20" }
→ 200 { "jeton": "1|abc…", "type": "Bearer", "expire_le": "…", "nouveau_compte": true, "utilisateur": {…} }
```

Les appels suivants envoient l'en-tête `Authorization: Bearer {jeton}`. Le jeton doit être gardé dans un stockage chiffré (EncryptedSharedPreferences ou Keystore). Il est valable 90 jours (`VIVI_API_JETON_JOURS`).

- `GET /moi` renvoie le compte connecté. La valeur `utilisateur.espace` vaut `client`, `pro` ou `agent` et indique les écrans à ouvrir. `est_proprietaire`, `est_gestionnaire` et `est_agent` affinent le menu (un compte peut avoir plusieurs rôles : un agent peut aussi réserver comme client, et un propriétaire peut être agent d'autres logements).
- `POST /auth/deconnexion` déconnecte cet appareil.
- `POST /auth/deconnexion-partout` déconnecte tous les appareils.
- Une réponse **401** signifie que le jeton est expiré ou révoqué. L'application revient alors à l'écran de connexion.

**Continuer avec Google (clients seulement)**

Si `GET /configuration` renvoie `connexion.google: true`, l'application peut proposer le bouton « Continuer avec Google » :

1. Avec Google Sign-In (Credential Manager, `GetGoogleIdOption`), demander un ID token en donnant comme `serverClientId` la valeur `connexion.google_client_id` (identifiant client Web de VIVI). L'application Android doit aussi avoir son propre identifiant client « Android » dans la console Google (nom du paquet + empreinte SHA-1).
2. `POST /auth/google` avec `{ "id_token": "…", "appareil": "Tecno Spark 20", "invitation": "GERA-7K3P" }` (`invitation` facultatif).
3. La réponse 200 a la même forme que `POST /auth/verifier` (`jeton`, `nouveau_compte`, `utilisateur`). Le compte est créé comme **client** s'il n'existe pas, ou relié à Google s'il existe déjà avec la même adresse email.
4. Erreurs 422 : `errors.id_token` (jeton refusé ou expiré), `errors.identifiant` (email Google non vérifié, compte désactivé, ou compte propriétaire, gestionnaire ou de l'équipe VIVI). Ces comptes gardent la connexion par code.

Le gestionnaire ne crée pas son compte lui-même. Le propriétaire l'invite (section 6), puis le gestionnaire se connecte en `mode: connexion` avec l'email ou le numéro donné par le propriétaire.

## 2. Erreurs

| Code | Sens | Corps |
|---|---|---|
| 422 | Saisie refusée ou règle métier | `{ "message": "…", "errors": { "champ": ["…"] } }` |
| 401 | Non connecté | `{ "message": "Connectez-vous pour continuer." }` |
| 403 | Rôle insuffisant | `{ "message": "Cet espace n'est pas accessible avec votre compte." }` |
| 404 | Introuvable, ou appartient à un autre compte | `{ "message": "Introuvable." }` |
| 409 | Scanner : vérification à refaire | `{ "message": "Vérifiez de nouveau le code du client." }` |
| 429 | Trop de demandes | `{ "message": "…" }` et l'en-tête `Retry-After` |

Les règles métier (créneau déjà pris, report impossible, solde insuffisant…) arrivent en 422 sous `errors.reservation`, `errors.paiement`, `errors.report`, `errors.annulation`, `errors.montant`, etc.

Limites : 120 requêtes par minute et par compte. Les envois de code sont plus limités (10 par heure par adresse, 5 par heure par identifiant).

## 3. Sans connexion

| Méthode | Chemin | Rôle |
|---|---|---|
| GET | `/configuration` | Villes, formules, types, équipements, services, connexion email/SMS active, délais de report, parrainage, contact WhatsApp, version minimale de l'application |
| GET | `/publicites` | Espaces publicitaires de l'application : voir « Publicité » plus bas |
| POST | `/publicites/{id}/vue` · `/publicites/{id}/clic` | Compte une vue ou un clic (204) |
| GET | `/logements` | Recherche : `ville, date, voyageurs, formule, budget, type[], equipement[], service[], tri (pertinence, prix, note, distance), lat, lng, page`. 20 résultats par page, `meta` et `links` de pagination, `criteres` |
| GET | `/logements/{slug}` | Fiche : photos, équipements, services, tarifs, `zone` approximative (cercle de 400 m) |
| GET | `/logements/{slug}/disponibilites` | Bloc « Réserver » : mêmes paramètres que la réservation. Renvoie les formules, les heures ou créneaux libres et le `devis`. Les choix incohérents sont corrigés ; il faut rappeler cette adresse à chaque changement |
| GET | `/logements/{slug}/avis` | Avis publiés, paginés |

L'adresse exacte et la position précise ne sont jamais envoyées avant le paiement.

### Logements et salles de fêtes ou de conférence

VIVI loue deux familles d'espaces. Les deux se réservent de la même façon (heure, demi-journée, journée, QR Code), avec les mêmes routes.

- **`GET /configuration`** :
  - `categories` : `[{valeur: "logement"}, {valeur: "salle"}]` ;
  - chaque type de `types_logement` porte `categorie` et `capacite_max` (20 pour un logement, 1 000 pour une salle). Types de salle :
    - `salle_fetes` ;
    - `salle_conference` ;
    - `salle_reunion` ;
    - `espace_plein_air` (jardin, terrasse) ;
    - `espace_complet` (maison ou villa entière louée pour un événement) ;
  - `modes_gestion` : `proprietaire` (« Je gère moi-même ») ou `vivi` (« Je confie la gestion à VIVI »), avec `choix` et `aide` à afficher ;
  - chaque formule a `budget` (logements) et `budget_salle` (salles, plus cher) pour le filtre de prix ;
  - `commission_pourcentage` vaut pour les logements. `commission_salles_tranches` (`[{jusqua, pourcentage}]`, `jusqua: null` pour « au-delà ») donne le taux des salles selon le montant (location + services). Le devis (`devis.taux_commission`, `devis.commission`) applique déjà le bon taux.
- **`GET /logements?categorie=salle`** ne renvoie que les salles, `categorie=logement` que les logements ; sans le paramètre, tout est renvoyé. Pour les salles, `voyageurs` filtre sur la capacité ; le champ s'intitule « Capacité » (paliers proposés : 20, 50, 100, 200, 300, 500 places et plus).
- **Fiche et liste** :
  - `categorie` (`logement` ou `salle`) ;
  - `est_salle` ;
  - `capacite_libelle` (« 300 places » ou « 2 voyageurs »).

  Pour une salle, `capacite` est un nombre de places ; `chambres` et `lits` valent 0 et ne s'affichent pas.
- **Réservation d'une salle : aucun nombre de personnes** (une salle se réserve entière) :
  - quand `est_salle` vaut `true`, masquer le compteur « Voyageurs » et ne pas envoyer `voyageurs` (ignoré s'il est envoyé). Il reste **obligatoire pour un logement** ;
  - même règle pour `POST /pro/logements/{id}/reservations` ;
  - chaque réservation porte `personnes` : « 2 voyageurs », ou `null` pour une salle. Afficher ce texte, pas `voyageurs` (gardé pour compatibilité, il vaut 1 pour une salle) ;
  - libellé « Heure de début » au lieu de « Heure d'arrivée », et « cette salle » au lieu de « ce logement ».
- **Journée d'une salle : elle finit le jour même** (un logement garde son départ le lendemain) :
  - `tarifs.journee.heure_arrivee` et `heure_depart` sont l'heure de début et l'heure de fin. Fin plus tard que début : même jour (08:00 à 22:00). Fin avant début : après minuit (10:00 à 02:00, « après minuit ») ;
  - `jours: 2` réserve deux jours à la suite, jusqu'à l'heure de fin du deuxième jour ;
  - afficher `creneau` et les dates de la réponse, sans recalculer la fin dans l'application.
- **Ouvert 24 h/24** (appartements et salles) :
  - `ouvert_24h` (vrai ou faux) sur chaque logement : afficher « 24 h/24 » sur la carte et « Ouvert 24 h/24 » dans la fiche, à la place de « De … à … » ;
  - disponibilités : `heures` va alors de `00:00` à `23:00`, et `duree_max` peut dépasser minuit (24 au plus). Afficher `aide` et `creneau` de la réponse (« Départ à 02:00 le lendemain »), sans recalculer la fin dans l'application ;
  - propriétaire : `tarifs.heure.ouvert_24h` est renvoyé par la fiche. `true` dans le POST ou le PUT enregistre 00:00 à 00:00 ; `heure_ouverture` et `heure_fermeture` ne sont alors plus à demander. Une fermeture égale à l'ouverture vaut aussi 24 h/24 ; une fermeture plus tôt que l'ouverture veut dire après minuit ;
  - si la fiche était à 24 h/24 et que le propriétaire choisit des horaires, envoyer `ouvert_24h: false` ou ne pas l'envoyer.
- **Menu du visiteur** : « Mes réservations » n'est plus affiché avant la connexion (Accueil, Logements, Salles, Compte). Une fois connecté, le client retrouve ses réservations.
- **Logo** : nouveau logo dans `public/images/logo/` (`vivi-symbole.svg`, `vivi-icone.svg`, `icone-512.png`…), à reprendre pour l'icône de l'application. Signature : « Appartements & salles. »
- **Menus** : le site a deux entrées séparées, « Logements » (`categorie=logement`) et « Salles » (`categorie=salle`). L'application doit faire de même.
- **Propriétaire** (`POST/PUT /pro/logements`) :
  - avec un type de salle, `capacite` va jusqu'à 1 000 ; `chambres`, `lits` et `unites` sont ignorés (forcés à 0, 0 et 1) ;
  - `mode_gestion` vaut `proprietaire` ou `vivi`. Avec `vivi`, l'équipe est prévenue et l'administration désigne un gérant VIVI, qui accueille et scanne. La fiche renvoie `mode_gestion`, `gerant_vivi {id, nom}` (null tant qu'il n'est pas désigné) et `gestion_vivi_demandee_le` ;
  - `gestion_remuneration` (lecture seule) : `{ type, valeur, libelle, contrat, contrat_le }`, ou `null` tant que l'administration ne l'a pas fixée. `type` vaut `pourcentage`, `fixe` (FCFA par réservation, d'un jour ou d'une période) ou `fixe_jour` (FCFA par jour réservé). Afficher `libelle` (« 15 % de la location ») et `contrat`. Ignoré s'il est envoyé dans le PUT ;
  - un gérant VIVI se connecte comme un gestionnaire (`espace: "pro"`) et voit les fiches qui lui sont confiées.
- **Quotas de l'abonnement**, séparés :
  - `GET /pro/logements` → `quota: {utilises, max, formule, salles: {utilises, max}}` ;
  - les formules (`GET /pro/abonnement`) ont `logements_max` et `salles_max`, soit au moins 2 logements et 1 salle ;
  - une salle au-delà du quota reste en brouillon.

### Publicité (publicités de VIVI et Google AdMob)

Si `GET /configuration` renvoie `publicite: true`, l'application appelle `GET /publicites?ville=Cotonou` (sans connexion ; `ville` facultatif : la ville recherchée ou choisie par le client).

```json
{ "data": {
  "active": true, "mention": "Publicité",
  "frequence_liste": 6, "plein_ecran_heures": 24, "admob_test": true,
  "emplacements": {
    "accueil_haut": {
      "libelle": "Accueil, en haut", "format": "banniere", "largeur": 1200, "hauteur": 400,
      "annonces": [ { "id": 3, "titre": "…", "annonceur": "Saveurs SARL", "texte": "…", "bouton": "En savoir plus",
                      "image": "https://…", "lien": "https://…", "lien_interne": false } ],
      "admob": { "format": "banniere_adaptative", "bloc": "ca-app-pub-…/…" }
    }
  }
} }
```

| Emplacement | Écran | Format | AdMob (`admob.format`) |
|---|---|---|---|
| `demarrage` | ouverture de l'application, fermable | `plein_ecran` 1080 × 1920 | `ouverture` (App Open) |
| `accueil_haut` | haut de l'accueil | `banniere` 1200 × 400 | `banniere_adaptative` |
| `accueil_milieu` | accueil, entre deux blocs | `carte` 1200 × 675 | `natif` |
| `recherche` | entre les résultats, tous les `frequence_liste` éléments | `carte` 1200 × 675 | `natif` |
| `fiche` | bas d'une fiche | `banniere` 1200 × 400 | `banniere_adaptative` |
| `reservations` | Mes réservations | `banniere` 1200 × 400 | `banniere_adaptative` |

Règles :
1. Si `annonces` n'est pas vide, les afficher (carrousel s'il y en a plusieurs). Sinon, si `admob` n'est pas null, afficher ce bloc. Sinon, **rien** : l'espace disparaît, sans trou.
2. Toujours afficher le texte `mention` (« Publicité ») sur une annonce de VIVI, avec `annonceur` s'il est donné.
3. `POST /publicites/{id}/vue` quand au moins la moitié de l'annonce est visible une seconde, une fois par affichage de l'écran. `POST /publicites/{id}/clic` au toucher. Réponse 204. Ne pas appeler ces adresses pour une annonce AdMob. Ne pas réessayer en cas d'erreur.
4. `lien_interne: true` : ouvrir l'écran de l'application ; `false` : navigateur externe ; `lien: null` : l'annonce ne s'ouvre pas.
5. `demarrage` : une fois toutes les `plein_ecran_heures` heures au plus, jamais à la première ouverture, et jamais si l'application est ouverte par une notification ou un lien.
6. **Jamais de publicité** sur la connexion, le paiement, le billet (QR Code), le scanner, ni dans les espaces propriétaire, gestionnaire et agent.

Google AdMob dans l'APK :
- SDK `com.google.android.gms:play-services-ads` et `com.google.android.ump:user-messaging-platform`.
- L'**identifiant de l'application** AdMob (`ca-app-pub-…~…`) se met dans le manifeste (`com.google.android.gms.ads.APPLICATION_ID`) à la compilation : l'API ne peut pas le donner. Sans lui, l'application **plante** au démarrage du SDK. En test : `ca-app-pub-3940256099942544~3347511713`.
- Les **blocs d'annonces** viennent de l'API : ne pas les écrire dans le code. Avec `admob_test: true`, l'API donne les blocs de démonstration de Google.
- Demander le consentement (UMP) avant `MobileAds.initialize()` : obligatoire pour les clients de l'Espace économique européen, du Royaume-Uni et de Suisse. Ajouter « Confidentialité des annonces » dans Compte si `privacyOptionsRequirementStatus` le demande.
- Ne charger le SDK que si au moins un emplacement a `admob` non null.
- Play Console : déclarer « Contient des annonces », l'identifiant publicitaire et la section Sécurité des données.

Le prix et le contact de l'annonceur ne sont jamais envoyés par l'API.

Politique de confidentialité : `GET /configuration` → `data.pages.confidentialite` donne son adresse, ou `null` tant qu'elle n'est pas publiée. L'afficher dans Compte › Aide, à côté de `pages.conditions` et `pages.annulation`, et la masquer si elle vaut `null`. La même adresse se met dans la fiche Play Store. `data.pages.suppression_compte` donne la page web de suppression du compte, à indiquer aussi dans la fiche Play Store.

Si `version_minimale` est plus récente que la version installée, l'application propose la mise à jour.

## 4. Client : réserver et payer

1. `POST /logements/{slug}/reserver` avec `{ date, formule, heure?, duree?, creneau_id?, jours?, voyageurs, agent? }` (`voyageurs` : logements seulement).
   `agent` est le code de l'agent marketing (`AG` + 6 caractères) lu dans le lien partagé `…/logements/{slug}?agent=AG…` (voir section 7). L'application doit le garder pour ce logement quand le client ouvre le lien, et le renvoyer ici. Un code invalide, d'un agent qui ne promeut pas ce logement, ou celui du client lui-même, est ignoré sans erreur.
   Le créneau est retenu jusqu'à `retenu_jusqua` (15 min par défaut). La réponse 201 contient la réservation, `services_proposes`, `devis`, `bonus` et `coordonnees`.
2. `POST /reservations/{ref}/devis` avec `{ "services": { "12": 2 }, "bonus": true }`. Le prix est recalculé à chaque changement de service.
3. `POST /reservations/{ref}/payer` avec `{ nom, whatsapp, email?, cgv: true, services: {id: quantite}, utiliser_bonus }`.
   - `whatsapp` est le numéro où le gérant peut joindre le client, de n'importe quel pays, avec l'indicatif (`+33 6 12 34 56 78`). Un numéro béninois peut se saisir sans indicatif (`01 97 00 00 00`).
   - Préremplissez le champ avec `utilisateur.whatsapp`.
   - Un numéro béninois devient aussi le téléphone du compte, s'il n'en avait pas, pour le QR Code et les rappels par SMS.
   - L'ancien champ `telephone` reste accepté.
   - Côté pro, `client.whatsapp` et `client.lien_whatsapp` (`https://wa.me/…`) ouvrent la conversation.
   La réponse 200 contient `{ url_paiement, paiement: { id, statut, termine, … } }`.
4. Ouvrir `url_paiement` dans un **Custom Tab** (de préférence) ou une WebView.
5. À la fin, le serveur renvoie vers le lien profond **`vivi://paiement/{id}?statut=reussi|echoue|en_attente`**. Il faut déclarer ce schéma dans l'`intent-filter` Android (réglable avec `VIVI_APP_LIEN_RETOUR`).
6. L'application appelle `GET /paiements/{id}` pour connaître le statut réel. Le paramètre `statut` du lien n'est qu'une indication. Tant que `termine` vaut `false`, réessayer toutes les 3 s pendant une minute environ. Le webhook FedaPay confirme de son côté.
7. `GET /reservations/{ref}` renvoie le billet.

Le billet (`GET /reservations/{ref}`) contient :
- `billet.qr_contenu` : le texte à transformer en QR Code. `billet.qr_svg` est aussi fourni.
- `billet.code_secours` : 6 chiffres.
- `logement.adresse` et `logement.itineraire` (lien Google Maps).

Ces trois éléments ne sont présents que si la réservation est **confirmée ou en cours**.

Le billet contient aussi `actions` : `payer`, `reporter`, `annuler`, `donner_avis`. Ce sont des booléens qui indiquent les boutons à afficher.

| Méthode | Chemin | Rôle |
|---|---|---|
| GET | `/reservations?onglet=avenir\|passees\|annulees` | Mes réservations |
| GET | `/reservations/{ref}/report` | Jours possibles et créneaux libres (même durée, dans la fenêtre de report) |
| POST | `/reservations/{ref}/report` | `{ date, option, accord: true }`. L'ancien QR Code devient invalide et la réservation n'est plus remboursable |
| GET | `/reservations/{ref}/annulation` | Montants : payé, commission (jamais remboursée), retenue, remboursé, règle appliquée |
| POST | `/reservations/{ref}/annuler` | `{ motif? }` |
| POST | `/reservations/{ref}/avis` | `{ note: 1..5, commentaire? }`. Possible après un séjour terminé ; l'avis est publié après vérification |

## 5. Compte (tous les rôles)

| Méthode | Chemin | Rôle |
|---|---|---|
| GET | `/profil` | Compte et statistiques |
| PUT | `/profil/nom` | `{ nom }` |
| POST | `/profil/changement` | `{ champ: email\|telephone, valeur }`. Un code est envoyé à la nouvelle adresse |
| POST | `/profil/changement/confirmer` | `{ code }` |
| PUT | `/profil/preferences` | `{ recevoir_offres: bool }`. Offres de VIVI acceptées ou refusées (champ `recevoir_offres` de l'utilisateur) |
| GET | `/notifications` | Liste paginée (20). Filtres : `?categorie=`, `?non_lues=1`, `?depuis=<ISO 8601>`. `meta.non_lues` = nombre de non lues |
| GET | `/notifications/compteur` | `{ non_lues, par_categorie: {reservation, paiement, offre, logement, compte}, derniere }` : pastille de la cloche, à appeler souvent |
| GET | `/notifications/{id}` | Une notification |
| POST | `/notifications/{id}/lu` · `/notifications/tout-lu` | Marquer comme lu |
| DELETE | `/notifications/{id}` | Supprimer |
| POST | `/appareils` | `{ jeton, plateforme?: android, nom? }` : jeton Firebase (FCM) du téléphone, pour le push. 201 la première fois, 200 ensuite |
| DELETE | `/appareils` | `{ jeton }` : ce téléphone ne reçoit plus de push |
| GET | `/parrainage` | Code, lien, message à partager, bonus disponible, historique, filleuls |

### Supprimer le compte (exigé par Google Play)

| Méthode | Chemin | Rôle |
|---|---|---|
| GET | `/compte/suppression` | `{ possible, obstacles: [{code, message}], pertes: [texte], canal, destination }` |
| POST | `/compte/suppression/code` | Envoie un code à `destination` (`canal` : `mail` ou `sms`). 422 sur `errors.compte` s'il y a un obstacle |
| POST | `/compte/suppression` | `{ code }` : supprime le compte. 422 sur `errors.code` ou `errors.compte` |

1. Compte › « Supprimer mon compte », pour tous les rôles.
2. `possible: false` : afficher les `message` des obstacles, sans bouton. Codes : `reservation`, `paiement`, `remboursement`, `accueil`, `retrait`, `solde`, `contact`, `equipe`.
3. `possible: true` : afficher `pertes`, puis demander le code et une confirmation claire.
4. Après la réponse 200, effacer le jeton d'accès et les données locales, puis revenir à l'accueil. Le jeton ne fonctionne plus (401).

### Notifications

Tout ce qui est envoyé à la personne (par email, SMS ou push) arrive aussi dans `/notifications`. Une notification :

```json
{ "id": "9c1e…", "type": "SejourCommence", "categorie": "reservation",
  "titre": "Bienvenue, votre séjour a commencé · VIV-A1B2C3", "texte": "Votre arrivée au Studio Lagune est enregistrée…",
  "icone": "bi-door-open", "cible": { "ecran": "reservation", "reference": "VIV-A1B2C3" },
  "lien": "https://…/reservation/VIV-A1B2C3", "action": "Voir ma réservation", "lue": false, "le": "2026-11-21T14:05:00+01:00" }
```

- **categorie** : `reservation`, `paiement`, `offre`, `logement`, `compte` (filtres et canaux Android).
- **cible.ecran** : l'écran à ouvrir quand on touche la notification. Valeurs possibles :
  - `reservation` : billet du client (`reference`) ;
  - `pro_reservation` : réservation côté pro (`reference`) ;
  - `avis` : laisser un avis (`reference`) ;
  - `parrainage` ;
  - `solde` : revenus et retraits (`reference` du retrait) ;
  - `agent` : espace agent ;
  - `pro_logement` (`id`) ;
  - `pro_accueil` ;
  - `offre` (`id` de l'offre ; le titre et le texte suffisent à l'afficher) ;
  - `notifications`.

  Un écran inconnu ouvre la liste des notifications, et `lien` est la page web équivalente.

| Type | Pour qui | Quand |
|---|---|---|
| `ReservationConfirmee` | client | paiement reçu |
| `SejourCommence` | client | QR Code scanné par le gérant : réservation activée |
| `RappelArrivee` | client | la veille de l'arrivée |
| `ReservationReportee` · `ReservationDeplacee` · `ReservationAnnulee` | client et pro | report, déplacement, annulation |
| `DemandeAvis` | client | fin du séjour |
| `RemboursementVerse` | client | remboursement versé |
| `BonusRecu` | client | bonus de parrainage |
| `OffreVivi` | selon la cible choisie par VIVI | offre ou annonce (Administration › Offres et annonces) ; jamais par SMS ni email, seulement si `recevoir_offres` |
| `NouvelleReservation` | propriétaire et gestionnaires | nouvelle réservation payée |
| `DecisionLogement` | propriétaire | logement publié, à corriger ou suspendu |
| `RetraitTraite` | propriétaire ou agent | retrait payé ou refusé |
| `CommissionAgent` | agent | client venu par son lien arrivé : commission créditée |
| `GestionConfiee` · `AgentDesigne` · `Bienvenue` | compte | rôle confié, création de compte |

### Push (Firebase Cloud Messaging)

`GET /configuration` → `data.push` vaut `true` quand le serveur est réglé pour Firebase.

1. Après la connexion, et à chaque `onNewToken`, l'application envoie `POST /appareils { jeton }`. Un jeton déjà connu passe au compte connecté.
2. Chaque notification arrive avec un bloc `notification` (titre, texte, canal Android `vivi_alertes`, à créer dans l'application) et un bloc `data`, uniquement des chaînes :
   - `notification_id` : l'`id` dans `/notifications`, pour la marquer lue ;
   - `type` ;
   - `categorie` ;
   - `ecran` ;
   - `reference` et `id`, selon la cible ;
   - `lien`.
3. À la déconnexion, envoyez `POST /auth/deconnexion { jeton_push }`. `deconnexion-partout` oublie tous les téléphones. Un jeton refusé par Firebase (application désinstallée) est retiré automatiquement.

Sans push (`data.push = false`), interrogez `GET /notifications/compteur`, puis `GET /notifications?depuis=<date de la dernière vue>`.

## 6. Propriétaire et gestionnaire (`/pro/…`)

Le **gestionnaire** voit seulement les logements qui lui sont confiés. Il a accès au tableau de bord, aux arrivées, au scanner, au calendrier et aux réservations.
Le reste est réservé au **propriétaire** et renvoie 403 au gestionnaire.

**Accueil du client**

| Méthode | Chemin | Rôle |
|---|---|---|
| GET | `/pro/tableau-de-bord` | Arrivées du jour, 7 prochains jours, revenus (mois, mois précédent, 7 jours), occupation. Pour le propriétaire seulement : solde, derniers retraits, abonnement |
| GET | `/pro/arrivees` | Clients attendus aujourd'hui |
| POST | `/pro/scanner/verifier` | `{ saisie }` : contenu du QR Code (`VIVI1.…`) ou code de secours à 6 chiffres. Réponse 200 avec `valide: true` et la fiche ; ou 422 avec `{ valide: false, titre, message }` |
| POST | `/pro/scanner/demarrer` | `{ reference }`, dans les 10 minutes qui suivent la vérification. Le séjour démarre et `credit` est ajouté au solde du propriétaire |

**Calendrier et réservations**

| Méthode | Chemin | Rôle |
|---|---|---|
| GET | `/pro/logements-geres` | Logements gérés, avec formules et créneaux (pour les listes de choix) |
| GET | `/pro/reservations?onglet=&logement=&page=` | Réservations, avec `meta.compteurs` par onglet |
| GET | `/pro/reservations/{ref}` | Détail, avec le client et son téléphone |
| GET | `/pro/logements/{id}/calendrier?mois=AAAA-MM&jour=AAAA-MM-JJ` | État de chaque jour (`libre`, `partiel`, `complet`, `bloque`) et réservations du jour choisi |
| POST | `/pro/logements/{id}/blocages` · DELETE `/pro/logements/{id}/blocages/{blocage}` | Bloquer ou débloquer une journée |
| POST | `/pro/reservations/{ref}/deplacer` | `{ date, heure }` : réservation consommée, ou à moins de 3 h de l'arrivée |
| POST | `/pro/logements/{id}/reservations` | Client venu hors de la plateforme : `{ formule, date, heure?, duree?, creneau_id?, jours?, voyageurs, nom, telephone?, montant?, encaisse?, note? }` |
| POST | `/pro/reservations/{ref}/encaisser` · `/retirer` | Encaisser `{ montant }` sur place, ou retirer une réservation hors plateforme |

**Propriétaire seulement**

| Méthode | Chemin | Rôle |
|---|---|---|
| GET · POST | `/pro/logements` | Liste (avec `quota`) ; création. Le logement part en vérification si l'abonnement le permet, sinon il reste en brouillon |
| GET · PUT | `/pro/logements/{id}` | Fiche complète ; modification. Le PUT reçoit les **mêmes champs** que la fiche renvoyée : `tarifs.heure\|demi_journee\|journee`, `creneaux[]`, `equipements[]` (ids), `services[]` (ids), `marketing_type` (`pourcentage`, `fixe` ou `null`) et `marketing_valeur` (50 % au plus, ou montant en FCFA). Si `marketing_type` est absent du PUT, la part marketing ne change pas |
| PATCH | `/pro/logements/{id}/en-ligne` | `{ en_ligne: false }` met le logement en pause |
| POST · DELETE | `/pro/logements/{id}/photos` · `/photos/{photo}` | Ajouter des photos (`photos[]` en multipart, 800 × 600 minimum, 5 Mo maximum, 15 par logement) ; supprimer une photo |
| POST | `/pro/logements/{id}/photos/{photo}/principale` | Choisir la photo principale |
| GET | `/pro/revenus` | Solde, gains du mois, mouvements (paginés), retraits |
| POST | `/pro/retraits` | `{ montant? }` : 1 000 FCFA minimum. Sans montant, tout le solde est demandé. Une caissière VIVI valide ensuite le retrait |
| GET · PUT | `/compte-retrait` | Compte où la caissière paie les retraits (propriétaire et agent marketing) : `{ moyen, numero, titulaire }`. `moyen` parmi `moyens` (MTN Mobile Money, Moov Money, Celtiis Cash, Virement bancaire). Obligatoire avant `POST /pro/retraits` ou `/agent/retraits` (sinon 422 sous `errors.compte`) ; il est recopié dans chaque demande (`retrait.compte`) |
| GET | `/pro/abonnement` | Abonnement en cours et suivant, formules avec leur tarif (promotion comprise), essai possible, paiements |
| POST | `/pro/abonnement/{formule}` | Paiement de l'abonnement : même suite que les étapes 4 à 6 de la section 4 |
| POST | `/pro/abonnement/{formule}/essai` | Essai gratuit, une fois par propriétaire |
| GET · POST | `/pro/gestionnaires` | Liste et maximum autorisé ; invitation `{ nom, identifiant, logements[] }` |
| PUT | `/pro/gestionnaires/{id}` | `{ logements: [] }` : une liste vide retire l'accès |
| GET | `/pro/agents?periode=mois\|annee\|tout` | Classement des agents marketing (rang, séjours réalisés, `a_venir`, `chiffre_location`, `gains`, logements promus) et part marketing de chaque logement |
| POST | `/pro/agents` | `{ nom, identifiant, logements[] }` : désigne un agent (nombre illimité). Un client existant garde son compte |
| PUT | `/pro/agents/{id}` | `{ logements: [] }` : une liste vide retire l'agent de vos logements ; ses commissions déjà gagnées lui restent |

Dans `GET /pro/reservations/{ref}`, `part_gestion` est la rémunération de VIVI quand la gestion lui est confiée (0 sinon), déjà retirée de `credit_proprietaire`.

Dans `GET /pro/reservations/{ref}`, `part_marketing` est le montant retiré de la location et `agent` (`{ id, nom }` ou `null`) dit à qui il est versé. Sans agent, la part revient à VIVI. `credit_proprietaire` tient déjà compte de cette part.

## 7. Agent marketing (`/agent/…`)

L'agent fait la promotion de logements choisis par leurs propriétaires. Il peut travailler pour plusieurs propriétaires, et un logement peut avoir plusieurs agents. Il n'accueille pas les clients : le scanner, le calendrier et les réservations restent au gestionnaire et au propriétaire (403 pour l'agent).

| Méthode | Chemin | Rôle |
|---|---|---|
| GET | `/agent` | `code_agent`, `solde`, `ce_mois`, `total_gagne`, `sejours`, `a_venir`, et pour chaque logement : `lien` à partager, `part` (`{ type, valeur, libelle }` ou `null`), `en_ligne`, `sejours`, `a_venir`, `gains`. Puis mouvements du solde (paginés) et retraits |
| POST | `/agent/retraits` | `{ montant? }` : comme `/pro/retraits` (1 000 FCFA minimum, validé par la caissière VIVI) |

Fonctionnement de la commission :
1. Le propriétaire règle la part marketing de chaque logement : pourcentage de la location, ou montant fixe par réservation (jamais plus que la location). Cette part est figée dans la réservation au moment où le client réserve.
2. Le client ouvre le lien de l'agent (`https://…/logements/{slug}?agent=AG…`). L'application Android doit déclarer ce lien en **App Link** pour l'ouvrir directement sur la fiche, et garder le code pour la réservation. Sur le site, il est retenu 30 jours par un cookie.
3. Au démarrage du séjour (QR Code scanné, ou séjour consommé si le client ne vient pas), la part est créditée sur le solde de l'agent. Le propriétaire reçoit la location moins cette part.
4. Sans agent, la part n'est versée à personne : elle reste à VIVI. Après une annulation, l'agent ne touche rien.
5. **L'agent qui réserve pour lui-même** un logement qu'il promeut (avec ou sans son lien) ne paie pas sa propre part : elle est déduite de son prix. Le `devis` du tunnel et les `montants` de la réservation contiennent alors `remise_agent` (déjà retirée de `total` et de `a_payer`). L'application affiche une ligne « Remise agent ». Il ne reçoit pas de commission sur ce séjour.

## 8. Essais en local

- `VIVI_PAIEMENT_PILOTE=simulation` : `url_paiement` ouvre une page « Paiement réussi / refusé ». Rien n'est débité.
- `APP_ENV=local` : le code de connexion est renvoyé dans `code_dev`, donc aucun email ni SMS n'est nécessaire.
- Comptes de démonstration : `*@demo.vivi` (`php artisan db:seed --class=DemoSeeder`).
- Depuis un téléphone sur le même Wi-Fi : `php artisan serve --host=0.0.0.0`, puis utiliser l'adresse IP du PC. `APP_URL` doit pointer vers cette adresse pour que les liens de paiement et de photos fonctionnent.
- Tests : `php artisan test --filter=ApiMobileTest` et `--filter=AgentsMarketingTest`.
