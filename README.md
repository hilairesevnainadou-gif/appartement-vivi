# VIVI · Appartements meublés

Plateforme web de location courte durée d'appartements meublés au Bénin : à l'heure, à la demi-journée ou à la journée, avec des services à la carte, un paiement Mobile Money et une entrée par QR Code.

Projet porté par **Nova Tech Bénin**. Ce dossier couvre la **phase 1** : la location courte durée sur le web.

## Contenu du dossier

```text
Appartement VIVI/
├── document/     cahier des charges, DATF, diagrammes, dossiers investisseur et juridique
├── maquette/     maquette HTML de référence (16 écrans) et pistes de logo
└── plateforme/   application Laravel en cours de développement
```

| Dossier | Rôle | Documentation |
| --- | --- | --- |
| `document/` | Exigences fonctionnelles et règles de gestion | Cahier des charges `Appartement VIVI.docx` (version de référence en cours de mise à jour) |
| `maquette/` | Interface validée : parcours, composants, charte graphique | [maquette/LISEZMOI.md](maquette/LISEZMOI.md) |
| `plateforme/` | Le code de l'application | [plateforme/README.md](plateforme/README.md) |

## Démarrage rapide

Prérequis : XAMPP (PHP 8.2 avec les extensions `intl` et `sodium`, MySQL démarré), Composer, Node.js 20 ou plus, `C:\xampp\php` dans le PATH.

**Voir la maquette** (aucune installation) :

- avec XAMPP : <http://localhost/Appartement%20VIVI/maquette/>
- sinon : ouvrir `maquette/index.html` dans un navigateur

**Lancer l'application :**

```bash
cd plateforme
composer install
npm install
cp .env.example .env            # renseigner DB_* et ADMIN_*
php artisan key:generate
php artisan migrate --seed      # rôles + premier administrateur
npm run build
php artisan serve               # http://localhost:8000
```

| Adresse | Contenu |
| --- | --- |
| <http://localhost:8000> | Site public |
| <http://localhost:8000/admin> | Administration (équipe VIVI) |

Les identifiants du premier administrateur viennent de `ADMIN_EMAIL` et `ADMIN_PASSWORD` dans `.env`. Sans mot de passe défini, le seeder en génère un et l'affiche une seule fois.

## Pile technique

| Brique | Choix |
| --- | --- |
| Application | Laravel 12, vues Blade, PHP 8.2 |
| Administration | Filament 5 |
| Rôles et permissions | Spatie Laravel Permission |
| Base de données | MySQL / MariaDB (base `vivi`) |
| Interface | Bootstrap 5.3, Bootstrap Icons, polices Outfit et DM Sans, compilés par Vite |
| Paiement (à venir) | FedaPay : MTN Mobile Money, Moov Money, Celtiis Cash, carte |
| Notifications (à venir) | SMS et email, chacun activable ou désactivable |
| Mobile (phase ultérieure) | Flutter, branché sur l'API de la même application |

## Rôles

| Rôle | Accès |
| --- | --- |
| Client | Recherche, réserve, paie, reporte, annule, note |
| Propriétaire | Gère ses logements, tarifs, créneaux, calendrier et abonnement ; scanne le QR Code |
| Gestionnaire de propriétaire | Gère les logements qu'on lui délègue, sans accès aux revenus |
| Caissière | Suit les encaissements et les remboursements ; valide les retraits des propriétaires |
| Administrateur | Supervise toute la plateforme ; peut aussi valider les retraits ; règle les paramètres |

L'Agent VIVI (inspections, longue durée) viendra dans une phase ultérieure.

## Modèle économique de la phase 1

- **Abonnement propriétaire :** mensuel, obligatoire pour publier un logement.
- **Commission de la plateforme :** à chaque réservation, le client paie, en plus du prix, la commission de VIVI. Elle est affichée avant le paiement.
- **Créneaux :** chaque propriétaire définit les formules, horaires et prix de chacun de ses logements.

### Paiements et retraits

1. Le client paie le total (location, services, commission) par **FedaPay** : Mobile Money ou carte.
2. Au scan du QR Code, le montant de la location s'ajoute au **solde du propriétaire**. La commission reste à VIVI.
3. Le propriétaire **demande un retrait** de son solde.
4. La **caissière ou l'administrateur valide** le retrait et indique le **mode de règlement** (Mobile Money, virement, espèces…).

### Règles de réservation

- **Report par le client :** gratuit, mais impossible à moins de **3 heures** du début du séjour. La nouvelle date tombe dans les 10 jours suivant la date prévue au départ.
- **Client absent ou hors délai :** la réservation est considérée comme **consommée**. Seuls le **propriétaire** (pour ses logements) ou l'**administrateur** peuvent encore la déplacer.
- **Annulation :** la **commission VIVI n'est jamais remboursée** (décision du 12/11/2026). La location et les services sont remboursés jusqu'à 24 h avant l'arrivée (heure, demi-journée) ou 5 jours avant (journée) ; ensuite, la location retenue revient au propriétaire.

### Paramétrable par l'administrateur

Sans toucher au code, depuis l'administration (à partir du lot 1) :

- le taux de la commission ;
- les formules d'abonnement (nom, prix, nombre de logements et de gestionnaires) ;
- le bénéficiaire des services à la carte (propriétaire ou VIVI) ;
- les notifications **SMS** et **email**, activables ou désactivables séparément.

Valeurs par défaut : `plateforme/config/vivi.php`.

## Charte graphique

| Élément | Valeur |
| --- | --- |
| Logo | Un V dont la branche droite est un immeuble, porte orange ; fichiers dans `maquette/assets/img/logo/` |
| Turquoise | `#136f6e` (marque, liens, boutons secondaires) |
| Turquoise foncé | `#0a3d3e` (textes de marque, fonds sombres) |
| Orange | `#f4ad4c` (action principale, porte du logo) |
| Titres / texte | Outfit / DM Sans |

## Avancement

| Étape | État |
| --- | --- |
| Analyse des documents et décisions de démarrage | Fait |
| Maquette (site client, espace client, espace propriétaire) | Fait, 16 écrans |
| Logo | Fait |
| Mise à jour du cahier des charges (V1.1) | En cours |
| Socle Laravel : base, rôles, administration, page d'accueil | Fait |
| Lot 1 : modèle de données, paramètres, administration Filament, données de démonstration | Fait (12 novembre 2026) |
| Lot 2 : comptes, connexion par code (email ou SMS), notifications selon les paramètres | Fait (12 novembre 2026) : email gratuit via Brevo, SMS en attente d'un compte fournisseur |
| Lot 3 : recherche, fiche logement, moteur de disponibilités, calcul du prix et de la commission | Fait (12 novembre 2026) |
| Lot 4 : tunnel de réservation, services, paiement FedaPay et webhook, QR Code signé, confirmation, reçu | Fait (12 novembre 2026) : paiement simulé en local, en attente des clés FedaPay sandbox |
| Lot 5 : espace propriétaire (logements, tarifs, créneaux, photos, calendrier, scanner du QR Code, déplacement hors délai, abonnement payé) | Fait (12 novembre 2026) |
| Lot 6 : espace client et après-vente (report, annulation et remboursement, avis, fin de séjour, client absent, rappel de la veille) | Fait (12 novembre 2026) |
| Lot 7 : caisse et rôles délégués (remboursements, déplacement par l'administrateur, exports comptables, gestionnaires, réservations et revenus du propriétaire) | Fait (12 novembre 2026) |

## Plan de développement de la phase 1

Estimation pour un développeur à plein temps, démarrage le 16 novembre 2026. Chaque lot se termine par un résultat testable.

| Lot | Contenu | Durée | Dates indicatives |
| --- | --- | --- | --- |
| 0. Socle | Laravel, base, rôles, administration, charte, logo | fait | fait |
| 1. Données, paramètres et administration | Tables (logements, tarifs, créneaux, préparation, blocages, services, réservations, paiements, soldes, retraits, abonnements, avis) ; page « Paramètres » (commission, formules, bénéficiaire des services, SMS et email) ; écrans Filament ; données de démonstration | fait | 12 nov. |
| 2. Comptes et notifications | Inscription client et propriétaire, connexion par code reçu par email ou SMS, envoi SMS et email selon les paramètres | fait | 12 nov. |
| 3. Recherche et fiche logement | Recherche et filtres, fiche logement, moteur de disponibilités, calcul du prix et de la commission côté serveur | fait | 12 nov. |
| 4. Réservation et paiement FedaPay | Services, blocage du créneau, paiement FedaPay et webhooks, QR Code signé et code de secours, confirmation, reçu | fait | 12 nov. |
| 5. Espace propriétaire | Logements et validation par l'admin, tarifs et créneaux, calendrier, scan, déplacement d'une réservation hors délai, solde, demande de retrait, abonnement | fait | 12 nov. |
| 6. Espace client et après-vente | Mes réservations, report (bloqué à moins de 3 h), annulation et remboursement, avis, tâches automatiques | fait | 12 nov. |
| 7. Caisse et rôles délégués | Validation des retraits par la caissière ou l'admin avec mode de règlement, remboursements, déplacement de réservation par l'admin, exports comptables, gestionnaire délégué | fait | 12 nov. |
| 8. Recette et mise en ligne | Tests de bout en bout, sécurité, performances, PWA, serveur OVH, SSL, sauvegardes, recette, première réservation réelle | 2 sem. | 16 → 27 févr. |

Mise en ligne estimée : **début mars 2027**.

À préparer en parallèle, côté Nova Tech : compte FedaPay (sandbox maintenant, compte réel validé avant le lot 8), compte Brevo pour l'email (gratuit, 300 emails par jour) et crédit SMS chez SMS.to ou un fournisseur local (avant la mise en ligne), avis du juriste sur l'encaissement pour le compte des propriétaires, serveur OVH et nom de domaine (avant le lot 8).

Après la phase 1 : applications mobiles Flutter (phase 2), location longue durée et agents VIVI (phase 3), rénovation (phase 4).

## Conventions

- Le code métier, les noms de tables, de routes et de vues sont **en français** (`logements`, `reservations`, `pages/accueil`), comme le cahier des charges.
- Les valeurs métier ne sont jamais en dur dans le code : elles viennent des paramètres de l'administration, avec `config/vivi.php` comme valeurs par défaut.
- Prix, disponibilités et remboursements sont **toujours calculés côté serveur** ; la maquette les calcule dans le navigateur pour la démonstration seulement.
- La maquette reste la référence visuelle : un nouvel écran se maquette d'abord dans `maquette/`, puis s'intègre en Blade.
#   a p p a r t e m e n t - v i v i  
 "# appartement-vivi" 
