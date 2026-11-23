<p align="center">
  <img src="plateforme/public/images/logo/vivi-logo-1200.png" alt="Appartement VIVI" width="340">
</p>

<h1 align="center">Appartement VIVI</h1>

<p align="center">
  Louer un appartement meublé ou une salle au Bénin, à l'heure, à la demi-journée ou à la journée.<br>
  Un projet de <strong>Nova Tech Bénin</strong>.
</p>

---

## Le projet en bref

Le client cherche un lieu, choisit son créneau et paie en ligne. Il entre avec un QR Code. Le propriétaire reçoit sa part quand le séjour commence.

| Partie | Pour qui | Adresse en local |
| --- | --- | --- |
| **Site public** | Clients | `http://localhost:8000` |
| **Espace propriétaire** | Propriétaires et gestionnaires | `http://localhost:8000/pro` |
| **Espace agent** | Agents marketing | `http://localhost:8000/agent` |
| **Administration** | Équipe VIVI | `http://localhost:8000/admin` |
| **Application Android** | Tous, sauf l'équipe VIVI | dossier `apk/` |

**Aller directement à :** [Parcours](#comment-ça-marche) · [Rôles](#qui-fait-quoi) · [Installation](#installer-et-lancer) · [Argent](#qui-paie-quoi) · [Règles](#règles-de-réservation) · [Avancement](#où-en-est-le-projet) · [Documentation](#où-trouver-la-suite)

## Comment ça marche

1. **Chercher.** Le client choisit une ville, une date et une formule : heure, demi-journée ou journée.
2. **Réserver.** Le créneau est retenu 15 minutes, le temps d'ajouter des services et de payer.
3. **Payer.** Le paiement se fait avec FedaPay : Mobile Money (MTN, Moov, Celtiis) ou carte.
4. **Entrer.** Le client montre son QR Code ou son code de secours. La personne qui l'accueille le scanne et le séjour commence.
5. **Donner son avis.** Après le départ, le client note le lieu.

## Qui fait quoi

| Rôle | Ce qu'il fait |
| --- | --- |
| **Client** | Cherche, réserve, paie, reporte, annule, donne son avis |
| **Propriétaire** | Publie ses logements et ses salles, fixe ses prix, suit son calendrier, demande ses retraits |
| **Gestionnaire** | Accueille les clients et scanne les QR Codes pour un propriétaire, sans voir ses revenus |
| **Gérant VIVI** | Fait de même quand le propriétaire confie la gestion à VIVI |
| **Agent marketing** | Partage un lien et touche une commission sur les réservations qu'il apporte |
| **Caissière** | Suit les encaissements, valide les retraits et les remboursements |
| **Technicien** | Lit la documentation, les alertes et le journal d'activité |
| **Administrateur** | Gère toute la plateforme et ses paramètres |

## Que contient ce dossier

```text
Appartement VIVI/
├── plateforme/   le site, l'administration et l'API (Laravel)
├── apk/          l'application Android (Kotlin)
├── maquette/     les écrans de référence, en HTML
└── document/     cahier des charges, diagrammes, dossiers
```

## Installer et lancer

### Il vous faut

- **XAMPP** avec PHP 8.2 (extensions `intl` et `sodium`) et MySQL démarré
- **Composer** et **Node.js 20** ou plus
- `C:\xampp\php` dans le PATH de Windows
- une base MySQL vide nommée `vivi`

### 1. Installer (une seule fois)

```bash
cd plateforme
composer install
npm install
cp .env.example .env
```

Ouvrez `.env` et remplissez `DB_*` (base de données) et `ADMIN_*` (premier administrateur). Pour écrire les emails dans un fichier au lieu de les envoyer, mettez `MAIL_MAILER=log`. Puis :

```bash
php artisan key:generate
php artisan storage:link
php artisan migrate --seed
npm run build
```

Si `ADMIN_PASSWORD` est vide, un mot de passe est créé et affiché une seule fois. En local, des données de démonstration sont ajoutées.

### 2. Lancer (à chaque fois)

```bash
php artisan serve            # le site, sur http://localhost:8000
php artisan schedule:work    # les tâches automatiques : rappels, fin des séjours…
php artisan queue:work       # les envois en attente
```

Une fenêtre de terminal par commande.

### 3. Vérifier

```bash
php artisan test
```

Les tests utilisent une base à part : ils ne touchent pas à vos données.

### L'application Android et la maquette

- **APK** : suivez [apk/README.md](apk/README.md).
- **Maquette** : ouvrez `maquette/index.html` dans un navigateur, rien à installer.

## Qui paie quoi

| Qui paie | Quoi | Valeur de départ |
| --- | --- | --- |
| **Client** | La location, les services et la commission de VIVI (calculée sur la location seulement) | Logement : 5 %. Salle : 10 % jusqu'à 100 000 FCFA, 8 % jusqu'à 500 000, 6 % au-delà |
| **Propriétaire** | Un abonnement mensuel pour publier | Selon la formule |
| **Propriétaire** | La gestion, s'il la confie à VIVI | Selon son contrat : pourcentage, prix fixe par réservation ou prix fixe par jour |
| **Propriétaire** | La part marketing, s'il en fixe une | À l'agent, ou à VIVI sans agent |
| **Annonceur** | Sa publicité dans l'application | Prix fixé par VIVI |

L'administrateur change les taux et les formules dans **Administration › Paramètres** et **Abonnements**, sans toucher au code.

**Le trajet de l'argent :**

1. Le client paie le total.
2. Au scan du QR Code, la part du propriétaire s'ajoute à son solde. La commission reste à VIVI.
3. Le propriétaire demande un retrait.
4. La caissière ou l'administrateur paie, puis valide avec le mode de règlement.

## Règles de réservation

| Situation | Règle |
| --- | --- |
| **Report** | Gratuit, une fois, jusqu'à 3 h avant l'arrivée. Nouvelle date dans les 10 jours. Plus de remboursement ensuite |
| **Annulation, heure ou demi-journée** | 24 h avant ou plus : location et services remboursés. Après : la location reste due |
| **Annulation, journée** | 5 jours avant ou plus : location et services remboursés. Après : le premier jour et la moitié des suivants restent dus |
| **Commission de VIVI** | Jamais remboursée |
| **Client absent** | La réservation est consommée. Seul le propriétaire ou l'administrateur peut encore la déplacer |
| **Salle** | Se réserve entière, sans nombre de personnes. La journée finit le jour même |

## Où en est le projet

### Fait

- Site public, espaces client, propriétaire, gestionnaire et agent
- Logements et salles, calendrier, QR Code, services à la carte
- Caisse, retraits, remboursements, exports comptables
- Administration, rôles, journal d'activité, carte, alertes
- API et application Android
- Notifications sur le téléphone, connexion Google pour les clients, publicités
- Suppression de compte et politique de confidentialité (à relire)

### À faire avant la mise en ligne

- [ ] **FedaPay** : compte réel et clés (le paiement est simulé en local)
- [ ] **SMS** : choisir et payer un fournisseur (en local, ils sont écrits dans un fichier)
- [ ] **Email** : compte Brevo
- [ ] **Juriste** : relire la politique de confidentialité et l'encaissement pour le compte des propriétaires
- [ ] **Contact** : renseigner WhatsApp et l'email dans Paramètres
- [ ] **Play Store** : signer l'application, remplir la fiche, créer les blocs AdMob
- [ ] **Serveur** : hébergement, nom de domaine, HTTPS, sauvegardes
- [ ] **Recette** : essais complets avec une première réservation réelle

Mise en ligne estimée : **début mars 2027**. Ensuite : location longue durée, puis rénovation.

## Outils utilisés

| Partie | Outils |
| --- | --- |
| Site et API | Laravel 12, PHP 8.2, Blade, Bootstrap 5.3, Vite |
| Administration | Filament 5 |
| Base de données | MySQL ou MariaDB |
| Application | Kotlin, Jetpack Compose, Android 8 ou plus |
| Services | FedaPay (paiement), Firebase (notifications), Google (connexion, AdMob), OpenStreetMap (cartes) |

## Où trouver la suite

| Je veux… | Je lis |
| --- | --- |
| Comprendre le code et les règles en détail | [plateforme/README.md](plateforme/README.md) |
| Utiliser l'API | [plateforme/docs/API-mobile.md](plateforme/docs/API-mobile.md) |
| Construire l'application Android | [apk/README.md](apk/README.md) |
| Voir les écrans de référence | [maquette/LISEZMOI.md](maquette/LISEZMOI.md) |
| Lire le cahier des charges | `document/Appartement VIVI.docx` |

## Pour les développeurs

- **Le code est en français** : tables, routes, vues et classes métier.
- **Rien en dur** : les réglages viennent de l'administration, avec `plateforme/config/vivi.php` comme valeurs de départ.
- **Le serveur calcule tout** : prix, disponibilités, remboursements. Le site et l'application affichent le résultat.
- **Pas de secret dans Git** : clés et mots de passe restent dans `.env` et `storage/app/`.
- **Charte** : turquoise `#136f6e`, turquoise foncé `#0a3d3e`, orange `#f4ad4c` ; polices Outfit et DM Sans.
