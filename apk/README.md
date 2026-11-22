# VIVI · application Android

Une seule application pour les **clients**, les **propriétaires** et les **gestionnaires**, branchée sur l'API mobile de la plateforme (`plateforme/routes/api.php`, documentation : `plateforme/docs/API-mobile.md`).

Kotlin, Jetpack Compose (Material 3), Retrofit + kotlinx.serialization, CameraX + ML Kit (scanner), ZXing (QR Code du billet), Custom Tabs (paiement FedaPay). Android 8.0 (API 26) minimum.

## Charte graphique

Les couleurs, polices et rayons reprennent exactement ceux de la maquette (`maquette/assets/css/vivi.css`, `vivi-pro.css`) :

| Jeton | Valeur | Usage dans l'application |
| --- | --- | --- |
| `Vivi.Teal700` | `#136f6e` | marque, liens, boutons secondaires, onglet actif |
| `Vivi.Teal900` | `#0a3d3e` | en-têtes sombres (accueil, tableau de bord, billet), QR Code |
| `Vivi.Orange400` | `#f4ad4c` | action principale (texte `#14282a`), bouton Scanner, porte du logo |
| `Vivi.Sand` | `#faf7f2` | fond des écrans |
| `Vivi.Line` | `#e2e8e7` | bordures des cartes |
| `Vivi.Success` / `Vivi.Danger` | `#1e7d4f` / `#b83a2b` | statuts, montants, erreurs |

Polices : **Outfit** (titres) et **DM Sans** (texte), en fichiers variables dans `res/font`. Les jetons sont dans `ui/theme/Couleurs.kt` ; les écrans n'utilisent jamais de couleur en dur. L'icône et l'écran de démarrage sont générés à partir des SVG du logo (`maquette/assets/img/logo`).

Accessibilité : cibles tactiles de 48 dp au moins, contraste AA, libellés vocaux sur toutes les icônes seules, champs avec libellé visible et erreur juste en dessous, états de chargement, d'erreur (avec « Réessayer ») et de liste vide sur chaque écran.

## Écrans

| Espace | Écrans | API |
| --- | --- | --- |
| Visiteur / client | Explorer (villes, date, voyageurs, formule, filtres), fiche logement, bloc Réserver | `GET /configuration`, `/logements`, `/logements/{slug}`, `/disponibilites`, `/avis` |
| | Connexion ou inscription par code (email ou SMS) | `POST /auth/code`, `/auth/renvoyer`, `/auth/verifier` |
| | Tunnel : services, coordonnées, bonus, conditions, paiement | `POST /logements/{slug}/reserver`, `/reservations/{ref}/devis`, `/payer` |
| | Suivi du paiement (retour `vivi://paiement/{id}`) | `GET /paiements/{id}` |
| | Mes réservations, billet (QR Code, code de secours, adresse, itinéraire), report, annulation, avis | `GET /reservations`, `/reservations/{ref}`, `/report`, `/annulation`, `POST /report`, `/annuler`, `/avis` |
| Tous | Compte (nom, email et téléphone confirmés par code), notifications, parrainage, déconnexion | `/profil`, `/notifications`, `/parrainage`, `/auth/deconnexion` |
| Tous | Supprimer mon compte (Compte › Mes données) : obstacles ou pertes, code de confirmation, case à cocher | `GET /compte/suppression`, `POST /compte/suppression/code`, `POST /compte/suppression` |
| Pro (propriétaire + gestionnaire) | Tableau de bord, scanner (caméra ou code de secours), réservations, détail (appel, encaissement, déplacement), calendrier (blocage d'un jour) | `/pro/tableau-de-bord`, `/pro/arrivees`, `/pro/scanner/*`, `/pro/reservations`, `/pro/logements/{id}/calendrier`, `/blocages` |
| Propriétaire seulement | Revenus et retraits, logements (en ligne / en pause), abonnement (paiement, essai), gestionnaires | `/pro/revenus`, `/pro/retraits`, `/pro/logements`, `/pro/abonnement`, `/pro/gestionnaires` |

L'espace s'ouvre selon `utilisateur.espace` (`client` ou `pro`). Le menu « Plus » masque au gestionnaire ce qui est réservé au propriétaire (l'API renvoie de toute façon 403).

Le propriétaire ajoute et modifie ses logements dans l'application (Plus › Mes logements) : informations, emplacement (position du téléphone), capacité, formules et tarifs, créneaux, équipements, services, part de l'agent marketing et photos (`POST/PUT /pro/logements`, `/photos`). Les photos sont réenregistrées en JPEG (2048 px au plus) avant l'envoi.

**Agent marketing** (`utilisateur.espace = agent`) : onglet « Mes liens » (`GET /agent`) avec son code, ses logements, le lien à partager, ses commissions et ses retraits (`POST /agent/retraits`). Le propriétaire désigne ses agents dans Plus › Agents marketing (`/pro/agents`). Un lien `…/logements/{slug}?agent=AG…` ouvre la fiche dans l'application ; le code est gardé 30 jours et envoyé avec la réservation (`agent`).

## Construire l'APK

Prérequis : Android Studio (JDK fourni), SDK Android 37.

```bash
# Version de test, branchée sur le serveur local (émulateur : 10.0.2.2 = le PC)
./gradlew assembleDebug
# → app/build/outputs/apk/debug/app-debug.apk

# Téléphone réel sur le même Wi-Fi : adresse IP du PC
./gradlew assembleDebug -PviviApiUrl=http://192.168.1.20:8000/api/v1/
```

Côté plateforme, pour un téléphone réel : `php artisan serve --host=0.0.0.0`, et `APP_URL=http://192.168.1.20:8000` dans `.env` pour que les liens de paiement et les photos fonctionnent. En local, le code de connexion est renvoyé dans `code_dev` et rempli automatiquement (bandeau « Mode test »).

Version de production (HTTPS obligatoire ; le trafic en clair n'est autorisé qu'en version de test) :

```bash
./gradlew assembleRelease -PviviApiUrlProd=https://vivi.bj/api/v1/
```

La signature de la version publiée sur le Play Store reste à configurer (`signingConfigs`, clé gardée hors du dépôt).

## Sécurité

- Jeton Bearer gardé dans `EncryptedSharedPreferences` (clé dans le Keystore Android) ; sauvegarde automatique désactivée (`allowBackup=false`).
- Une réponse 401 ferme la session et ramène à l'écran d'accueil visiteur.
- Compte supprimé : jeton, notifications, push, verrouillage par empreinte, liens d'agent et compte Google retenu sont effacés du téléphone.
- Le statut d'un paiement est toujours relu chez le serveur (`GET /paiements/{id}`) ; le paramètre `statut` du lien de retour n'est qu'une indication.
- Le QR Code est généré sur le téléphone à partir du jeton signé par le serveur (`billet.qr_contenu`) ; la lecture par le scanner se fait sur l'appareil (ML Kit), sans envoyer d'image.

## Organisation du code

```text
app/src/main/java/bj/vivi/app/
├── MainActivity.kt          démarrage, lien profond vivi://paiement
├── data/                    API (Retrofit), modèles JSON, session chiffrée, erreurs
└── ui/
    ├── theme/               couleurs, polices, formes de la charte
    ├── composants/          boutons, puces, cartes, champs, états (vivi.css)
    ├── Navigation.kt        onglets client / pro, routes
    ├── auth/                connexion par code
    ├── client/              explorer, fiche, tunnel, paiement, billet, report, annulation
    ├── compte/              profil, notifications, parrainage
    └── pro/                 tableau de bord, scanner, réservations, calendrier, revenus, logements, abonnement, gestionnaires
```
