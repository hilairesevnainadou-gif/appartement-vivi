# Appartement VIVI · plateforme web

Plateforme de location courte durée d'appartements meublés (heure, demi-journée, journée) au Bénin. Phase 1 du projet porté par Nova Tech Bénin.

La maquette validée est dans `../maquette/`. Le cahier des charges et les décisions du README général font référence pour les règles de gestion.

## Pile technique

| Brique | Choix | Version |
| --- | --- | --- |
| Framework | Laravel | 12 |
| PHP | XAMPP | 8.2 (extensions `intl` et `sodium` activées) |
| Base de données | MySQL / MariaDB (XAMPP) | base `vivi`, utf8mb4 |
| Administration | Filament, sur `/admin` | 5 |
| Rôles et permissions | Spatie Laravel Permission | 6 |
| Front public | Blade + Bootstrap 5.3 + Bootstrap Icons, compilés par Vite | |
| Polices | Outfit (titres), DM Sans (texte) | via npm, servies localement |

## Installation sur un nouveau poste

PHP doit être dans le PATH (`C:\xampp\php`), et MySQL démarré dans XAMPP.

```bash
composer install
npm install
cp .env.example .env          # puis renseigner DB_* et ADMIN_*
php artisan key:generate
php artisan storage:link      # rend les photos des logements accessibles
php artisan migrate --seed    # rôles, administrateur, catalogue ; en local, données de démonstration
npm run build                 # ou « npm run dev » pendant le développement
php artisan serve             # http://localhost:8000
```

Sans `ADMIN_PASSWORD` dans `.env`, le seeder génère un mot de passe et l'affiche une seule fois.

Pour repartir d'une base propre : `php artisan migrate:fresh --seed`.

## Données de démonstration

En environnement `local`, `php artisan migrate --seed` ajoute les données de la maquette : 8 logements (dont un en attente de validation) avec leurs tarifs, créneaux, photos, équipements et services, 6 réservations, 1 avis à modérer et 1 retrait à valider. On peut les relancer seules avec `php artisan db:seed --class=DemoSeeder`. Elles sont refusées en production.

Comptes de démonstration, tous avec le mot de passe `demo-vivi-2026` :

| Compte | Identifiant | Rôle |
| --- | --- | --- |
| Caissière VIVI | `caisse@demo.vivi` | Caissière (peut entrer dans `/admin`) |
| Rodrigue Houngbo | `rodrigue@demo.vivi`, 01 97 00 00 01 | Propriétaire (3 logements, abonnement Confort) |
| Aïcha Dossou | `aicha@demo.vivi`, 01 97 00 00 02 | Propriétaire (5 logements, abonnement Premium) |
| Serge Agbo | `serge@demo.vivi`, 01 97 00 00 03 | Gestionnaire (Studio Lagune) |
| 5 clients | 01 97 00 00 11 à 15 | Client |

## Organisation du code

```text
app/
├── Enums/                         rôles, types et statuts (logement, réservation, paiement, retrait…)
├── Filament/
│   ├── Concerns/AccesParRole.php  qui voit et qui modifie chaque écran de l'administration
│   ├── Pages/Parametres.php       page « Paramètres » (commission, notifications, report…)
│   ├── Resources/                 écrans d'administration, un dossier par sujet
│   └── Widgets/ChiffresDuJour.php chiffres du tableau de bord
├── Http/Controllers/
│   ├── Auth/ConnexionController.php  inscription et connexion par code (email ou SMS)
│   ├── Public/                    site public : accueil, recherche, fiche logement
│   ├── Client/                    espace client (lot 6)
│   └── Proprietaire/              espace propriétaire (lot 5)
├── Notifications/                code de connexion, bienvenue, canal SMS, choix des canaux selon les paramètres
├── Sms/                          envoi des SMS : JournalSms (écrit dans le journal) ou SmsTo
├── Models/                        Logement, Tarif, Creneau, Reservation, Paiement, Retrait…
├── Services/Caisse.php            solde des propriétaires et retraits
├── Services/CodesConnexion.php    création et vérification des codes à 6 chiffres
├── Services/Disponibilites.php    moteur de disponibilités (réservations, préparation, jours bloqués)
├── Services/Tarification.php      calcul du prix et de la commission (Devis)
├── Services/SimulationSejour.php  état du bloc « Réserver » de la fiche logement
├── Services/Reservations.php      retenue du créneau, paiement, confirmation, déplacement, libération
├── Services/Arrivees.php          scanner : vérification du QR Code ou du code de secours, démarrage du séjour
├── Services/GestionLogements.php  logements saisis par les propriétaires (tarifs, créneaux, photos, quota)
├── Services/Abonnements.php       souscription, renouvellement, activation au paiement, expiration
├── Services/ApresVente.php        report, annulation et remboursement, avis, fin de séjour, rappels
├── Services/Delegations.php       gestionnaires délégués (invitation, logements confiés, limite de la formule)
├── Paiement/                      passerelles : FedaPayPasserelle (bibliothèque officielle) et SimulationPasserelle
└── Support/
    ├── Parametres.php             lecture des paramètres (base, sinon config/vivi.php)
    ├── Montant.php                affichage des montants en FCFA
    └── Telephone.php              numéros béninois à 10 chiffres (01…), format +229
config/vivi.php                    valeurs par défaut des paramètres métier
database/
├── migrations/                    tables regroupées par sujet (logements, services et abonnements, réservations, finances)
└── seeders/                       rôles, administrateur, catalogue, démonstration
resources/
├── css/vivi.css, vivi-pro.css     charte reprise de la maquette
└── views/                         gabarits, partiels et pages du site public
tests/Feature/                     paramètres, caisse, administration, connexion, SMS, espaces client et propriétaire
```

## Modèle de données

| Sujet | Tables |
| --- | --- |
| Logements | `logements`, `equipements`, `equipement_logement`, `logement_photos`, `tarifs` (une ligne par formule), `creneaux` (demi-journée), `blocages`, `delegations` (gestionnaires) |
| Catalogue et abonnements | `services`, `logement_service`, `formules_abonnement`, `abonnements` |
| Réservations | `reservations`, `reservation_services`, `paiements` (FedaPay, réservation ou abonnement), `avis` |
| Finances | `mouvements_solde`, `retraits` |
| Réglages | `parametres` |

Règles inscrites dans le modèle :

- **Montants en FCFA entiers**, sans centimes.
- **Montants figés à la réservation :** taux de commission, prix des services et bénéficiaire des services sont copiés dans la réservation. Changer un paramètre ne modifie jamais une réservation passée.
- **Solde du propriétaire calculé :** c'est la somme de `mouvements_solde`. Un séjour démarré le crédite ; une demande de retrait le débite tout de suite ; un retrait refusé est recrédité.
- **Créneau retenu pendant le paiement :** une réservation en attente de paiement occupe le créneau jusqu'à `bloque_jusqua`.

## Administration (`/admin`)

| Menu | Écrans | Administrateur | Caissière |
| --- | --- | --- | --- |
| Logements | Logements (validation, tarifs, créneaux, jours bloqués, photos), services à la carte, équipements | Tout | Consultation des logements et des services |
| Réservations | Réservations, avis clients | Consultation, modération des avis | Consultation des réservations |
| Finances | Paiements reçus, retraits propriétaires | Tout | Tout, dont la validation des retraits |
| Abonnements | Abonnements, formules d'abonnement | Tout | Abonnements ; consultation des formules |
| Administration | Utilisateurs, paramètres | Tout | Aucun accès |

Les droits sont définis dans chaque ressource (`$rolesLecture`, `$rolesEcriture`, `$actionsInterdites`) et appliqués par `App\Filament\Concerns\AccesParRole`.

## Recherche, fiche logement et prix

| Adresse | Écran |
| --- | --- |
| `/logements` | Résultats : ville, date, voyageurs, formule, budget, type, équipements, services, tri. Seuls les logements publiés, en ligne et **libres à la date choisie** apparaissent. |
| `/logements/{slug}` | Fiche logement et bloc « Réserver ». Un logement non publié n'est visible que de son propriétaire, de son gestionnaire et de l'équipe VIVI (bandeau « Aperçu »). |
| `/logements/{slug}/disponibilites` | JSON appelé par le bloc « Réserver » à chaque changement : heures libres, créneaux, nombre de jours possible, prix détaillé. |

**Disponibilités** (`App\Services\Disponibilites`). Un séjour est libre s'il commence dans plus de 30 minutes, ne touche aucun jour bloqué et ne chevauche aucune réservation qui occupe le logement (confirmée, en cours, ou en attente de paiement dont le créneau est encore retenu), en gardant entre deux séjours le temps de préparation du logement.

**Prix** (`App\Services\Tarification`). Le serveur est la seule source de vérité :

- heure : prix × nombre d'heures, entre l'ouverture et la fermeture, au moins la durée minimum. Fermeture avant l'ouverture = après minuit. **Ouvert 24 h/24** = fermeture égale à l'ouverture (`Tarif::est24h()`, case dans la fiche du propriétaire) : arrivée à toute heure, séjour de 24 h au plus, qui peut passer minuit ;
- demi-journée : prix du créneau choisi ;
- journée : prix × nombre de jours (arrivée le jour J, départ le jour J + n) ;
- services à la carte : seulement ceux proposés dans le logement, au prix du catalogue ;
- commission VIVI : taux des paramètres, appliqué à la location seulement (pas aux services), arrondi au franc.

## Réservation et paiement

1. **Fiche logement, « Continuer »** (`POST /reserver/{slug}`) : le créneau est vérifié sous verrou puis retenu pour le client pendant 15 minutes (paramètre « blocage du créneau »). Sans compte, le client passe par la connexion et revient sur la fiche avec ses choix.
2. **Tunnel** (`/reservations/{référence}/paiement`) : services à la carte (prix recalculé par le serveur), coordonnées, conditions, puis « Payer ».
3. **Paiement** : le client part sur la page de paiement FedaPay (ou la page simulée en local), puis revient sur `/paiements/{id}/retour`. Le statut est **toujours relu chez FedaPay** ; le webhook `POST /webhooks/fedapay` confirme aussi, signature vérifiée. La confirmation n'a lieu qu'une fois.
4. **Billet** (`/mes-reservations/{référence}`) : QR Code signé, code de secours à 6 chiffres, adresse exacte, reçu imprimable. Le client et le propriétaire (et ses gestionnaires) sont prévenus par email et/ou SMS selon les paramètres.
5. **Créneaux abandonnés** : `php artisan vivi:liberer-creneaux`, planifiée toutes les 5 minutes (cron `schedule:run` en production).

Le QR Code contient `VIVI1.{référence}.{signature}` : la signature dépend de la clé de l'application et du code de secours, elle ne peut pas être fabriquée. `Reservation::referenceDuJeton()` la vérifie (scanner du propriétaire, lot 5).

### Brancher FedaPay

En local, `VIVI_PAIEMENT_PILOTE=simulation` : une page VIVI propose « paiement réussi » ou « refusé », rien n'est débité. Cette simulation est refusée en production. Pour FedaPay :

```dotenv
VIVI_PAIEMENT_PILOTE=fedapay
FEDAPAY_ENVIRONMENT=sandbox          # live en production (jamais une clé ; la clé publique pk_ ne sert pas)
FEDAPAY_SECRET_KEY=sk_sandbox_...    # Développeurs > Clés API
FEDAPAY_WEBHOOK_SECRET=wh_sandbox_... # Développeurs > Webhooks, adresse https://<domaine>/webhooks/fedapay
```

Le webhook doit être joignable depuis Internet (en local, passer par un tunnel de type ngrok ou Expose). Sans webhook, le retour du client suffit à confirmer ; le webhook couvre le client qui ferme son navigateur avant de revenir.

## Géolocalisation

- **Fiche du logement (propriétaire)** : carte Leaflet / OpenStreetMap (`resources/js/carte.js`). « Utiliser ma position » ou clic sur la carte ; l'adresse, le quartier et la ville sont proposés (Nominatim) et restent modifiables. Position enregistrée dans `logements.latitude/longitude`.
- **Fiche publique** : zone d'environ 400 m dont le centre est décalé de ~150 m (`Logement::positionApprochee`) : le logement est dans la zone, sans être désigné. L'adresse exacte n'est donnée qu'après paiement.
- **« Près de moi »** (accueil, recherche) : la position du navigateur n'est pas enregistrée ; `/logements?lat=…&lng=…` trie du plus proche au plus loin (`App\Support\Geo`), rayon de 25 km sans ville choisie, sinon les plus proches.

## Rôles, permissions et journal d'activité

- **Permissions** (`App\Support\Permissions`) : deux par écran de l'administration (`{slug}.voir`, `{slug}.gerer`), plus `administration.acceder`, `parametres.gerer`, `exports.telecharger`. L'administrateur a tout (`Gate::before`). Administration › Rôles et permissions : créer un rôle (comptable, superviseur…) et cocher ses permissions ; `php artisan db:seed --class=RoleSeeder` crée les permissions manquantes sans retirer celles ajoutées.
- **Journal d'activité** (spatie/laravel-activitylog) : création, modification, suppression des logements, réservations, retraits, abonnements, paiements, utilisateurs, paramètres…, connexions et échecs de connexion, changements de rôles et de permissions. Administration › Journal d'activité (filtres, détail avant / après). Conservé un an : purge planifiée chaque nuit (`activitylog:clean`, routes/console.php).

## Carte des logements et surveillance

- **Administration › Logements › Carte et progression** (permission `carte.voir`) : logements inscrits sur la carte, colorés par statut (filtres), curseur et lecture animée mois par mois, couverture par ville (logements, quartiers, unités en ligne), nouveaux logements par mois.
- **Surveillance** (`App\Services\Surveillance`, seuils dans `config/vivi.php` › `surveillance`) : erreurs 500 en série, mots de passe faux en série sur l'administration, codes de connexion faux en série, accès aux fichiers visés par les robots (`.env`, `wp-login.php`…), pages inexistantes en série, limites dépassées, webhook FedaPay à signature invalide, paiements échoués en série, tâches en échec, disque presque plein, base injoignable, tâches planifiées arrêtées (production). Les incidents identiques sont regroupés (compteur).
- **Alertes** : Administration › Alertes système (traiter, détails, conseils) ; bandeau en tête du tableau de bord ; cloche de l'administration (notifications en base) ; email aux administrateurs ; SMS en plus si critique ; au plus un message toutes les 30 min par alerte. Base en panne : journal Laravel et email de secours (`VIVI_EMAIL_ALERTES`).
- **Contrôle régulier** : `php artisan vivi:surveiller` toutes les 5 min (planifié).

## Salles de fêtes et de conférence

Depuis le 22/11/2026, deux types sont ajoutés à `App\Enums\TypeLogement` : `salle_fetes` et `salle_conference`. Une salle est un `Logement` comme les autres : même réservation, mêmes tarifs, même QR Code et même abonnement.

Ce qui change pour une salle :
- `capacite` est un nombre de **places** (jusqu'à 1 000, contre 20 pour un logement) ;
- `chambres`, `lits` et `unites` sont forcés à 0, 0 et 1 (`LogementRequest`) ;
- **aucun nombre de personnes à la réservation** : une salle se réserve entière. `Logement::regleVoyageurs()` rend `voyageurs` facultatif (enregistré à 1) et `Reservation::personnes()` renvoie `null` ;
- les textes s'accordent avec `Logement::mot('le'|'ce'|'du')` : logement, salle ou espace ;
- menus : « Logements » et « Salles et événements » sont deux entrées séparées (en-tête, barre mobile, pied de page) ; la page des salles porte `data-page=« salles »` ;
- la recherche filtre avec `?categorie=logement|salle` (onglets de `/logements`, choix « Un logement / Une salle » à l'accueil) ;
- le filtre de budget a ses propres bornes (`Formule::budget(salle: true)`).

Il existe 5 types de salles :
- `salle_fetes` ;
- `salle_conference` ;
- `salle_reunion` ;
- `espace_plein_air` ;
- `espace_complet` (maison ou villa entière louée pour un événement).

**Quotas** : chaque formule a `logements_max` et `salles_max`, au moins 2 logements et 1 salle. Ils sont comptés séparément par `GestionLogements::quota()` et vérifiés au changement de formule (`Abonnements::souscrire`).

**Gestion confiée à VIVI** (`logements.mode_gestion` = `proprietaire` | `vivi`, `App\Enums\ModeGestion`) :
1. Dans sa fiche, le propriétaire choisit « Je gère moi-même » ou « Je confie la gestion à VIVI ».
2. L'équipe reçoit une alerte. L'administration désigne le gérant VIVI (`gerant_vivi_id`) avec le bouton « Désigner le gérant VIVI » de la fiche. Il s'agit d'un compte gestionnaire de l'équipe.
3. Le gérant voit les arrivées et le calendrier, et scanne (`Arrivees::logementsDe`). Il reçoit les notifications de réservation (`Logement::equipeAccueil()`). Il ne compte pas dans les gestionnaires du propriétaire.

**Rémunération de VIVI pour la gestion** (`App\Enums\RemunerationGestion`, migration `2026_11_22_300000`) :
- pourcentage de la location, prix fixe **par réservation** (le même pour un jour ou une période) ou prix fixe **par jour réservé** (une période de 3 jours le compte 3 fois ; à l'heure ou à la demi-journée : un jour), selon le contrat signé avec le propriétaire ;
- l'administration le saisit dans la fiche ou dans « Désigner le gérant VIVI » (type, valeur, référence et date du contrat). Le propriétaire le voit dans sa fiche, sans pouvoir le changer ;
- la règle et le montant sont figés dans chaque réservation (`gestion_type`, `gestion_valeur`, `montant_gestion`) : un nouveau contrat ne touche pas les réservations déjà faites ;
- le montant est retiré du crédit du propriétaire au démarrage du séjour (libellé « gestion VIVI : −X »), après la part marketing, et ne dépasse jamais ce qui lui revient ;
- rien n'est retenu sur les réservations hors plateforme ni sur la location gardée après une annulation tardive ;
- filtre « Contrat de gestion à fixer » dans les listes, colonne « Gestion VIVI » dans les exports, montant compté dans les revenus VIVI.

**Journée d'une salle** : elle finit le jour même (exemple : 08:00 à 22:00). Si l'heure de fin est avant l'heure de début, elle finit après minuit (exemple : 10:00 à 02:00). Un logement garde son départ le lendemain (`Disponibilites::periodeJournees(..., salle: true)`).

**Commission des salles par tranches** :
- paramètre `commission_salles_tranches`, réglé dans Administration › Paramètres ;
- le taux dépend du montant de la réservation (location + services) ;
- la tranche se lit sur la location, et son taux s'applique à toute la location (`Parametres::commissionPourcentage($logement, $montant)`) ;
- valeurs par défaut : jusqu'à 100 000 FCFA 10 %, jusqu'à 500 000 FCFA 8 %, au-delà 6 % ;
- les logements gardent le taux unique `commission_pourcentage` ;
- le taux est figé dans chaque réservation (`taux_commission`).

**Administration** :
- listes séparées : « Logements » (`/admin/logements`) et « Salles et espaces » (`/admin/salles`, `App\Filament\Resources\Salles\SalleResource`, permissions `salles.voir` et `salles.gerer`). Le formulaire et les actions sont communs ; `LogementResource::urlFiche()` ouvre la bonne liste ;
- une seule carte, « Carte et progression » (`/admin/carte`), avec les onglets Tout, Logements, Salles et espaces (`?categorie=logement` ou `salle`) ; `/admin/carte-salles` y redirige ;
- après déploiement, lancer `php artisan db:seed --class=RoleSeeder` pour créer les permissions des salles ;
- filtres type, gestion et « Gérant VIVI à désigner » ;
- widget « Parc » (logements et salles publiés, gérés par VIVI, gérants à désigner) ;
- quotas de salles dans les formules ;
- salles comptées sur la carte.

Les propriétaires créent une salle avec « Ajouter une salle » (`/pro/logements/nouveau?type=salle`). Les données de démonstration (`DemoSeeder`) contiennent 5 salles, dont 2 gérées par VIVI. La migration du 22/11/2026 ajoute des équipements (sonorisation, vidéoprojecteur…) et des services (décoration, traiteur, sécurité, nettoyage) propres aux salles.

**Menus et pied de page** : « Mes réservations » n'apparaît plus sur le site public pour un visiteur. Le client connecté les retrouve dans son menu. Le pied de page est dans `resources/views/partials/pied.blade.php` (le contact vient des paramètres).

**Villes** : le bandeau de l'accueil cite les 3 villes les plus fournies puis « et N autres villes » ; le pied de page en montre 8 au plus. La liste « Où ? » les garde toutes.

**Publicité de l'application** (`App\Support\Publicites`, `App\Models\Publicite`, migration `2026_11_22_400000`) :
- Administration › Publicités (application) : image, texte, lien, espace, période, villes, vues et clics par jour (`publicite_jours`) ;
- Administration › Paramètres › Publicité dans l'application : activation, source par espace (VIVI, AdMob, les deux, rien), blocs AdMob, mode test ;
- `GET /api/v1/publicites` sert les espaces à l'APK ; le site public n'affiche pas de publicité ;
- `/app-ads.txt` est créé à partir de l'identifiant éditeur AdMob. Il doit répondre à la racine du domaine indiqué comme site web sur le Play Store ;
- après déploiement : `php artisan migrate`, `php artisan db:seed --class=RoleSeeder` (permissions `publicites.voir`, `publicites.gerer` et `publicites.encaisser`), `php artisan storage:link` si ce n'est pas fait.

**Règlements des annonceurs** (`App\Models\PublicitePaiement`, migration `2026_11_22_500000`) :
- chaque publicité a un prix (0 = gratuite) et un contact d'annonceur ; l'API ne les envoie jamais ;
- « Encaisser » enregistre un règlement, en une ou plusieurs fois, sans dépasser le reste à payer ; il s'annule dans l'onglet des règlements ;
- chiffres du mois, filtre « À encaisser », export CSV `publicites` ;
- une publicité déjà encaissée ne se supprime plus ; une publicité impayée n'est pas coupée automatiquement ;
- ces montants ne sont pas comptés dans « Revenus VIVI » ;
- la caissière lit la liste et encaisse.

**Suppression du compte** (`App\Services\SuppressionCompte`, migration `2026_11_22_600000`) :
- depuis « Mon profil » ou l'application, confirmée par un code ; page publique `/aide/supprimer-mon-compte` (lien à donner à Google Play) ;
- refusée tant qu'il reste une réservation, un paiement, un remboursement, un client attendu, un retrait ou un solde de 1 000 FCFA ou plus ; refusée pour l'équipe VIVI et les gérants VIVI ;
- la ligne du compte reste, sans donnée personnelle (`supprime_le`) : réservations, paiements et retraits sont des pièces comptables. Le journal d'activité du compte et de ses fiches est vidé de ses valeurs. Les avis affichent « Ancien client » ;
- propriétaire : fiches suspendues, photos effacées, délégations retirées, abonnement arrêté ; agent : les commissions des séjours pas encore commencés reviennent à VIVI ;
- les comptes supprimés n'apparaissent plus dans Administration › Utilisateurs.

**Politique de confidentialité** (`/aide/confidentialite`) : projet à faire relire. Le public reçoit une erreur 404 tant que l'option n'est pas activée dans Paramètres › Contact et pages légales (`confidentialite_publiee`). Le texte change si AdMob est actif. Après une modification du texte, changer aussi la date en bas de la page.

**Logo** : `public/images/logo/` (SVG et PNG), avec l'arche d'une salle à côté du V et de l'immeuble. L'ancien logo est gardé dans `docs/logo-precedent/`.

## Résidences et réservations hors plateforme

- **Résidence à plusieurs chambres** : champ « Chambres identiques à louer séparément » (`logements.unites`, 1 par défaut). Le logement reste réservable tant qu'une unité est libre : `Disponibilites::unitesLibres` compte le maximum de séjours simultanés (temps de préparation compris). Un jour bloqué bloque toutes les unités. Une annonce par type de chambre si les prix diffèrent.
- **Réservation hors plateforme** : dans `/pro/calendrier`, « Ajouter une réservation hors plateforme » (propriétaire ou gestionnaire) pour un client venu par téléphone ou sur place (`Reservations::ajouterManuelle`, `reservations.origine = manuelle`). Elle occupe le calendrier, sans paiement en ligne ni commission ; le montant encaissé est noté, rien n'est crédité sur le solde. Pas de billet ni de rappel pour le client ; « Retirer du calendrier » libère la période ; non scannée, elle passe « terminée » à la fin.

## Services à la carte : qui encaisse le prix

Par défaut le prix des services revient à VIVI (Administration › Paramètres, « Par défaut, le prix des services à la carte revient à »). Exception par logement : dans la fiche du logement, « Services assurés par le propriétaire » ; le prix de ces services-là lui revient. Le bénéficiaire est figé sur chaque service réservé (`reservation_services.beneficiaire`), et la part du propriétaire (`reservations.montant_services_proprietaire`) lui est créditée avec la location au démarrage du séjour (`Reservation::creditProprietaire`). Le propriétaire voit « Le prix vous revient » ou « Encaissé par VIVI » sur chaque service.

## Promotions et parrainage (réglages : Administration › Paramètres)

| Fonction | Règle |
| --- | --- |
| Essai gratuit des abonnements | durée et formule réglables ; une fois par propriétaire, s'il n'a jamais eu d'abonnement ; payer la même formule prolonge après l'essai (`Abonnements::commencerEssai`) |
| Remise sur les abonnements | pourcentage, nom de l'offre, dates de début et de fin, « premier abonnement payé seulement » ; prix barrés sur `/proprietaires` et `/pro/abonnement` (`Abonnements::tarif`) |
| Code d'invitation | chaque utilisateur a un code (`GERA-7K3P`) et un lien `/connexion?invitation=…` (page `/parrainage`) ; l'ami le saisit à l'inscription et peut recevoir un bonus de bienvenue |
| Bonus du parrain | crédité au premier séjour démarré de l'ami (QR Code scanné), une seule fois par ami, avec une durée de validité (`Parrainage::recompenser`) |
| Utilisation du bonus | « Utiliser mon bonus » au paiement, jusqu'à un pourcentage du total ; FedaPay encaisse le reste ; bonus rendu si le paiement n'aboutit pas, et rendu avant l'argent en cas d'annulation remboursée. Le propriétaire reçoit sa location entière : le bonus est à la charge de VIVI |
| Suivi | Administration › Finances › Parrainage et bonus (liste, « Offrir un bonus ») ; colonne « Payé avec bonus » dans l'export des réservations |

## Caisse et rôles délégués

| Où | Qui | Quoi |
| --- | --- | --- |
| Administration › Finances › Remboursements clients | administrateur, caissière | file des remboursements dus après annulation (commission déduite) ; « Verser » avec le mode et la référence ; le client est prévenu |
| Administration › Réservations › fiche | administrateur | « Déplacer » une réservation confirmée ou consommée, même durée, logement libre ; le client est prévenu |
| Administration › Finances › Exports comptables | administrateur, caissière | CSV par période : réservations, paiements, retraits, remboursements, synthèse par propriétaire (`/admin-exports/{type}`) |
| `/pro/reservations` | propriétaire, gestionnaire | toutes les réservations, par onglet et par logement |
| `/pro/revenus` | propriétaire | solde, historique des mouvements, retraits, demande de retrait d'un montant choisi |
| `/pro/gestionnaires` | propriétaire | confier l'accueil (scanner, calendrier, réservations) de certains logements à une personne, dans la limite de la formule ; retirer l'accès |
| `/pro/agents` | propriétaire | agents marketing : classement (mois, année, depuis le début), désignation sans limite, logements promus par chacun, part marketing de chaque logement |
| `/agent` | agent marketing | liens d'affiliation à copier ou partager sur WhatsApp, commissions par logement, solde, retrait (`/agent/retraits`) |

## Agents marketing (`App\Services\AgentsMarketing`)

| Point | Règle |
| --- | --- |
| Part marketing | réglée par le propriétaire dans la fiche du logement (onglet Tarifs) : pourcentage de la location (50 % au plus) ou montant fixe par réservation (plafonné à la location). Figée dans la réservation (`marketing_type`, `marketing_valeur`, `montant_marketing`) |
| Qui la paie | elle est prise sur la location du propriétaire, avec ou sans agent (`Reservation::creditProprietaire`) |
| Qui la reçoit | l'agent dont le lien `/logements/{slug}?agent=AG…` a amené le client (`reservations.agent_id`), crédité au démarrage du séjour (`TypeMouvement::CreditMarketing`, même solde et mêmes retraits qu'un propriétaire). Sans agent : VIVI |
| Lien | code personnel `users.code_agent`, retenu 30 jours par logement dans le cookie `vivi_agent` (le dernier lien ouvert l'emporte) ; l'application l'envoie dans `agent` à la réservation |
| Agent client | un agent qui réserve un logement qu'il promeut paie le prix moins sa part (`reservations.remise_agent`, déjà déduite de `montant_total`) ; ni commission pour lui, ni part pour VIVI |
| Ignoré | code inconnu, agent qui ne promeut pas ce logement, annulation, réservation hors plateforme |
| Administration | tableau de bord : « Revenus VIVI du mois » = commission + part marketing sans agent, et « Part marketing du mois » (VIVI / agents) ; fiche réservation : part et bénéficiaire ; exports : colonnes marketing dans les réservations, export « Synthèse par agent marketing » |

## Documentation technique dans l'administration

Administration › Documentation technique (`/admin/documentation`) affiche ce README et `docs/API-mobile.md`, relus à chaque visite. Permission « documentation.voir » : l'administrateur l'a toujours ; le rôle **Technicien** (accès à l'administration, documentation, alertes et journal, sans les finances) l'a au départ. On donne le rôle dans Administration › Utilisateurs, et on peut cocher la permission pour d'autres rôles dans Rôles et permissions.
| Accès | l'agent ne voit ni le calendrier, ni les clients, ni le scanner ; un agent peut aussi être client, gestionnaire ou propriétaire d'autres logements |

## Après-vente (`App\Services\ApresVente`)

| Action | Règle |
| --- | --- |
| Report (`/mes-reservations/{réf}/reporter`) | gratuit jusqu'à 3 h avant l'arrivée (paramètre), vers une date libre dans les 10 jours suivant la date prévue au départ (paramètre) ; même formule et même durée ; nouveau code de secours, donc nouveau QR Code ; une réservation reportée n'est plus remboursable |
| Annulation (`/mes-reservations/{réf}/annuler`) | la commission VIVI n'est jamais remboursée. Heure et demi-journée : location et services remboursés jusqu'à 24 h avant, ensuite seuls les services ; journée : location et services remboursés jusqu'à 5 jours avant, ensuite la première journée et la moitié des suivantes restent dues. La location retenue est créditée au propriétaire. Le remboursement est versé par la caisse |
| Avis | après un séjour terminé, une fois ; publié après relecture par VIVI (administration › Avis clients) |
| Fin de créneau (`vivi:cloturer-sejours`, toutes les 5 min) | séjour en cours → terminé, puis demande d'avis ; client jamais arrivé → **consommée**, la location revient au propriétaire |
| Rappel (`vivi:rappels`, tous les quarts d'heure) | la veille de l'arrivée, une seule fois : heure, adresse, code de secours, dernier délai de report |

## Espace propriétaire (`/pro`)

| Écran | Rôle | Ce qu'il fait |
| --- | --- | --- |
| Tableau de bord `/pro` | propriétaire, gestionnaire | arrivées du jour, revenus, occupation, solde et retraits (propriétaire) |
| Mes logements `/pro/logements` | propriétaire | création et modification : informations, équipements, formules et tarifs, créneaux de demi-journée, temps de préparation, services, photos ; mise en pause |
| Calendrier `/pro/calendrier` | propriétaire, gestionnaire | mois et détail du jour ; bloquer ou débloquer une journée ; **déplacer** une réservation consommée (client absent) ou à moins de 3 h de l'arrivée |
| Scanner `/pro/scanner` | propriétaire, gestionnaire | QR Code (caméra) ou code de secours ; démarre le séjour et crédite le solde (location, plus services s'ils sont attribués au propriétaire) |
| Abonnement `/pro/abonnement` | propriétaire | formule en cours, renouvellement, changement de formule, historique ; paiement par la même passerelle que les réservations |

Règles :

- **Abonnement obligatoire** : un logement n'est visible des clients (recherche, fiche, réservation) que si son propriétaire a un abonnement en cours. Un nouveau logement part en vérification si la formule le permet (nombre de logements), sinon il reste en brouillon. `php artisan vivi:expirer-abonnements` (chaque nuit) marque les abonnements échus.
- **Renouvellement** : la même formule prolonge la période en cours ; une autre formule commence au paiement et remplace la précédente (sans prorata).
- **Scanner** : 20 vérifications par minute au plus (pour empêcher de deviner un code de secours) ; seule la réservation vérifiée à l'instant peut être démarrée ; le code de secours n'est jamais affiché au propriétaire.
- **Caméra** : BarcodeDetector du navigateur, sinon jsQR. Elle demande une connexion https (ou localhost).
- **Photos** : JPEG, PNG ou WebP, 800 × 600 pixels au moins, 5 Mo au plus, 15 par logement, dans `storage/app/public/logements/{id}/`.

## Comptes et connexion

Pas de mot de passe pour les clients et les propriétaires : on saisit son email ou son numéro, on reçoit un code à 6 chiffres, on le tape sur `/connexion/code`.

- **Inscription et connexion** sur `/connexion` (`?mode=inscription&role=proprietaire` pour arriver directement sur l'inscription propriétaire). Une fois connecté, le client va sur `/mon-compte`, le propriétaire ou le gestionnaire sur `/pro`, l'équipe VIVI sur `/admin`.
- **Sécurité du code :** valable 10 minutes, 5 essais au plus, renvoi possible après 30 secondes, enregistré sous forme d'empreinte (jamais en clair), utilisable une seule fois. Limites : 10 demandes par heure et par adresse IP, 5 par heure et par identifiant.
- **Canaux :** si les SMS (ou les emails) sont désactivés dans les paramètres, la connexion par ce canal est refusée avec un message clair.
- **En local**, le code s'affiche aussi à l'écran (`VIVI_AFFICHER_CODE_LOCAL=true`) : inutile d'ouvrir le journal. Jamais en production.

### Connexion avec Google (clients seulement)

Bouton « Continuer avec Google » sur `/connexion`, et `POST /api/v1/auth/google` pour l'application (`App\Services\ConnexionGoogle`, `Auth\GoogleController`). Le compte est créé comme client, ou relié s'il existe avec le même email (`users.google_id`). Les comptes propriétaire, gestionnaire et équipe VIVI sont refusés : ils gardent le code. Un agent marketing (aussi client) est accepté. Le code d'invitation de parrainage est conservé.

Pas de bibliothèque externe : Laravel Socialite a été écarté le 21/11/2026, car Composer bloque sa dépendance `firebase/php-jwt` (avis de sécurité). Le service fait lui-même l'échange OAuth 2.0 et vérifie l'ID token auprès de Google (`tokeninfo` : émetteur, destinataire, expiration, email vérifié).

Mise en route (console.cloud.google.com › API et services) :

1. Écran de consentement OAuth : nom « VIVI », logo, email d'assistance, domaine autorisé.
2. Identifiants › Créer un identifiant client OAuth › **Application Web**. URI de redirection autorisée : `{APP_URL}/connexion/google/retour` (par exemple `http://localhost:8000/connexion/google/retour` en local, puis l'adresse https du site).
3. Dans `.env` : `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` (et `GOOGLE_REDIRECT_URI` si l'adresse diffère de `APP_URL`), puis `php artisan config:clear`.
4. Pour l'APK : un identifiant client **Android** (nom du paquet + SHA-1 du certificat de signature), ajouté dans `GOOGLE_CLIENTS_ANDROID`.

Sans `GOOGLE_CLIENT_ID`, le bouton reste affiché : un clic indique que la connexion Google n'est pas encore activée.

### Notifications (espace Notifications, push, offres)

Chaque notification (trait `CanauxSelonParametres`) part :
- dans l'espace Notifications (base de données) ;
- en **push** sur l'application Android (`CanalPush` → `App\Services\NotificationsPush`, Firebase HTTP v1, sans bibliothèque externe) ;
- par email ou SMS, selon la page Paramètres.

Elle porte une `categorie` et une `cible` (écran de l'application) : voir `docs/API-mobile.md`.

Depuis le 21/11/2026, de nouvelles notifications sont envoyées :
- `SejourCommence` : QR Code scanné ;
- `CommissionAgent` ;
- `RetraitTraite` : payé ou refusé ;
- `DecisionLogement` : publié, à corriger ou suspendu.

Les **offres** (Administration › Offres et annonces, `App\Services\Offres`) vont dans l'espace Notifications et en push, jamais par SMS ni email. Seuls les comptes avec `recevoir_offres` les reçoivent ; ce choix se règle dans le profil, sur le site comme dans l'application.

Réglage du push :
1. Créer un projet sur console.firebase.google.com et y ajouter l'application Android (son `google-services.json` va dans l'APK).
2. Ouvrir Paramètres du projet › Comptes de service › Générer une nouvelle clé privée.
3. Placer le fichier JSON hors de `public/`, par exemple `storage/app/firebase.json`.
4. Renseigner `FIREBASE_PROJECT_ID` et `FIREBASE_CREDENTIALS=storage/app/firebase.json`.

Sans ce réglage, rien n'est envoyé en push : l'application interroge `/api/v1/notifications/compteur`.

### Espaces connectés

- **`/mon-compte`** (client) : « Mes réservations » en trois onglets (à venir, passées, annulées), report bloqué à moins de 3 h.
- **`/pro`** (propriétaire ou gestionnaire) : tableau de bord de la maquette avec les vraies données (arrivées du jour, revenus, occupation, prochaines réservations, solde, demande de retrait, abonnement). Gabarit `<x-layouts.pro>`.
- Les écrans des lots suivants sont déjà dans les menus : un clic affiche « arrive dans une prochaine étape » (attribut `data-bientot`).

### Envoi des emails (gratuit avec Brevo)

En local, les emails partent vers le serveur SMTP de DevLauncher (`MAIL_HOST=127.0.0.1`, `MAIL_PORT=2525`) et s'affichent dans DevLauncher. Sans DevLauncher, mettre `MAIL_MAILER=log` : les emails sont alors écrits dans `storage/logs/laravel.log`. Pour envoyer de vrais emails, créer un compte gratuit sur brevo.com (300 emails par jour), valider le domaine d'envoi, puis dans `.env` :

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=identifiant SMTP fourni par Brevo
MAIL_PASSWORD=clé SMTP fournie par Brevo
```

### Envoi des SMS

Aucun fournisseur n'envoie gratuitement des SMS au Bénin sur la durée. Par défaut `VIVI_SMS_PILOTE=log` : le SMS est écrit dans le journal au lieu d'être envoyé. Pour envoyer de vrais SMS avec SMS.to (compte d'essai gratuit, puis paiement à l'usage) :

```dotenv
VIVI_SMS_PILOTE=smsto
SMSTO_API_KEY=clé API SMS.to
SMSTO_EXPEDITEUR=VIVI
```

Changer de fournisseur (un agrégateur béninois par exemple) revient à écrire une classe qui implémente `AppSmsSms` et à l'ajouter dans `AppServiceProvider`.

## Paramètres métier

Modifiables par l'administrateur dans **Administration › Paramètres**, sans toucher au code : taux de commission, bénéficiaire des services à la carte, notifications SMS et email, délai de report (3 h), fenêtre de report (10 jours), durée de blocage du créneau, villes, modes de règlement des retraits.

Dans le code, on les lit avec `App\Support\Parametres::get('cle')`. Sans valeur enregistrée, c'est celle de `config/vivi.php` qui s'applique.

## API de l'application mobile

`/api/v1` (fichier `routes/api.php`, contrôleurs `app/Http/Controllers/Api/V1`, ressources `app/Http/Resources/Api`). C'est une seule API pour les clients, les propriétaires et les gestionnaires. On s'y connecte par code à 6 chiffres, et l'application reçoit un jeton Laravel Sanctum. La logique métier est partagée avec le site (`App\Services\Inscriptions`, `Recherche`, `TableauDeBord`, `SuiviPaiements`). Un paiement lancé depuis l'application revient par `/paiements/{id}/retour-app`, qui ouvre le lien profond `vivi://paiement/{id}`.

La documentation pour le développeur de l'application est dans [docs/API-mobile.md](docs/API-mobile.md).

## Tests

```bash
php artisan test
```

Les tests utilisent une base SQLite en mémoire : ils ne touchent pas à la base `vivi`.
