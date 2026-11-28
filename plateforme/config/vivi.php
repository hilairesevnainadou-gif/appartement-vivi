<?php

/*
|--------------------------------------------------------------------------
| Paramètres métier d'Appartement VIVI
|--------------------------------------------------------------------------
| Valeurs PAR DÉFAUT. Au lot 1, les réglages marqués « paramétrable » passent
| dans une page « Paramètres » de l'administration : l'administrateur les
| modifie sans toucher au code. Ce fichier sert alors de valeur initiale.
|
| Décisions du 11/11/2026 :
| - commission de la plateforme payée par le client sur chaque réservation ;
| - commission, formules d'abonnement et bénéficiaire des services : paramétrables ;
| - notifications SMS et email activables ou désactivables ;
| - report impossible à moins de 3 heures du début du séjour ;
| - paiements par FedaPay ; retraits des propriétaires validés par la caissière
|   ou l'administrateur, qui indique le mode de règlement.
*/

return [

    // Contact du service client (paramétrable) : numéro WhatsApp et message proposé au client
    'contact' => [
        'whatsapp' => env('VIVI_WHATSAPP', ''),
        'whatsapp_message' => 'Bonjour VIVI, j’ai une question :',
        'email' => env('VIVI_EMAIL_CONTACT', ''),
        // Pied de page : adresse, téléphone et réseaux sociaux (vides : masqués)
        'adresse' => '',
        'telephone' => '',
        'reseaux' => ['facebook' => '', 'instagram' => '', 'tiktok' => '', 'linkedin' => '', 'youtube' => '', 'x' => ''],
    ],

    // Application mobile (paramétrable) : liens affichés sur le site. Lien vide : « Bientôt disponible ».
    // APK : sans lien saisi, le fichier public/telechargements/vivi.apk est proposé s'il est présent.
    'application' => [
        'afficher' => true,
        'apk_lien' => '',
        'apk_version' => '',
        'play_store' => '',
        'app_store' => '',
    ],

    // Promotions sur les abonnements des propriétaires (paramétrables)
    'abonnement_promo' => [
        // Essai gratuit : une fois par propriétaire, avant tout abonnement payé
        'essai_actif' => false,
        'essai_jours' => 30,
        'essai_formule_id' => 0,          // 0 : au choix du propriétaire
        // Remise sur les abonnements payés entre deux dates
        'actif' => false,
        'pourcentage' => 20,
        'debut' => '',
        'fin' => '',
        'libelle' => 'Offre de lancement',
        'nouveaux_seulement' => true,     // seulement pour le premier abonnement payé
    ],

    // Parrainage : un client invite un ami ; au premier séjour de l'ami, le parrain reçoit un bonus (paramétrable)
    'parrainage' => [
        'actif' => false,
        'bonus_parrain' => 2000,          // FCFA, au premier séjour démarré du filleul
        'bonus_filleul' => 1000,          // FCFA, à la création du compte avec le code (0 : aucun)
        'validite_jours' => 90,           // durée de validité d'un bonus
        'plafond_pourcentage' => 50,      // part maximale du total d'une réservation payable avec le bonus
    ],

    // Politique de confidentialité : publiée par l'administrateur après relecture (paramétrable)
    'confidentialite_publiee' => false,

    // Publicité dans l'application Android (paramétrable) : publicités de VIVI et Google AdMob
    'publicite' => [
        'active' => false,
        'frequence_liste' => 6,           // une publicité tous les 6 résultats de recherche
        'plein_ecran_heures' => 24,       // délai minimum entre deux publicités plein écran
        'emplacements' => [],             // par espace : source et bloc AdMob (App\Enums\EmplacementPub)
        'admob' => [
            'actif' => false,
            'test' => true,               // blocs de démonstration de Google tant que l'application n'est pas en ligne
            'editeur' => '',              // pub-0000000000000000 : sert au fichier /app-ads.txt
        ],
    ],

    // Surveillance (App\Services\Surveillance) : seuils d'alerte, sur une fenêtre glissante
    'surveillance' => [
        'erreurs_serveur' => [10, 300],          // 10 erreurs 500 en 5 min : critique
        'connexions_admin' => [5, 600],          // 5 échecs de connexion admin depuis une même adresse en 10 min
        'codes_faux' => [10, 900],               // 10 codes de connexion faux depuis une même adresse en 15 min
        'pages_introuvables' => [40, 300],       // 40 pages inexistantes depuis une même adresse en 5 min : exploration
        'limites' => [20, 600],                  // 20 refus « trop de requêtes » depuis une même adresse en 10 min
        // Robots (wp-admin, .env…) : simple information, sans message ; adresse bloquée après 5 essais en 10 min
        'chemins_attaque' => [5, 600],
        'blocage_robot_minutes' => 60,           // court : une adresse mobile peut être partagée par plusieurs clients
        'paiements_echoues' => [10, 3600],       // 10 paiements échoués en 1 h
        'renotifier_minutes' => 30,              // même alerte : pas plus d'un message toutes les 30 min
        'disque_minimum_pourcent' => 10,
        // Alerte si les tâches planifiées ne tournent plus (cron absent) : en production seulement
        // Vide ou absent : automatique (oui en production) ; sinon true / false
        'verifier_planificateur' => in_array(env('VIVI_SURVEILLER_PLANIFICATEUR'), [null, ''], true)
            ? env('APP_ENV') === 'production'
            : filter_var(env('VIVI_SURVEILLER_PLANIFICATEUR'), FILTER_VALIDATE_BOOL),
        // Destinataire de secours si la base de données est injoignable
        'email_secours' => env('VIVI_EMAIL_ALERTES', env('ADMIN_EMAIL')),
    ],

    // Villes ouvertes à la réservation (paramétrable)
    'villes' => ['Cotonou', 'Abomey-Calavi', 'Porto-Novo', 'Ouidah'],

    // Commission de la plateforme, payée par le client sur chaque réservation, en % (paramétrable)
    'commission_pourcentage' => (float) env('VIVI_COMMISSION', 5),

    // Salles de fêtes, de conférence… : commission par tranches du montant (location + services), en % (paramétrable).
    // Le taux de la tranche s'applique à tout le montant ; la dernière tranche (jusqua = null) vaut « au-delà ».
    'commission_salles_tranches' => [
        ['jusqua' => 100000, 'pourcentage' => 10],
        ['jusqua' => 500000, 'pourcentage' => 8],
        ['jusqua' => null, 'pourcentage' => 6],
    ],

    // Bénéficiaire du prix des services à la carte : 'proprietaire' ou 'vivi' (paramétrable)
    // Par défaut le prix revient à VIVI ; exception par logement pour les services que le propriétaire assure lui-même
    'services_beneficiaire' => 'vivi',

    // Durée de blocage d'un créneau pendant le paiement, en minutes
    'blocage_creneau_minutes' => 15,

    'report' => [
        // Le report n'est plus possible à moins de N heures du début du séjour
        'delai_minimum_heures' => 3,
        // La nouvelle date doit tomber dans les N jours suivant la date prévue au départ
        'fenetre_jours' => 10,
        // Passé le délai, ou client absent : réservation consommée. Seuls ces rôles
        // peuvent encore la déplacer (décision du 12/11/2026). Le propriétaire ne
        // déplace que les réservations de ses propres logements.
        'deplacement_hors_delai_par' => ['proprietaire', 'administrateur'],
    ],

    // Canaux de notification, activables ou désactivables (paramétrable)
    'notifications' => [
        'sms' => (bool) env('VIVI_NOTIF_SMS', true),
        'email' => (bool) env('VIVI_NOTIF_EMAIL', true),
    ],

    // Connexion des clients, propriétaires et gestionnaires par code à usage unique
    'connexion' => [
        'code_validite_minutes' => 10,
        'tentatives_max' => 5,          // essais de code avant de devoir en demander un nouveau
        'renvoi_secondes' => 30,        // délai minimal entre deux envois au même identifiant
        // En local seulement : affiche le code à l'écran pour tester sans email ni SMS
        'afficher_code_en_local' => (bool) env('VIVI_AFFICHER_CODE_LOCAL', true),
    ],

    // Envoi des SMS : 'log' (écrit dans storage/logs, gratuit, pour le développement) ou 'smsto'
    'sms' => [
        'pilote' => env('VIVI_SMS_PILOTE', 'log'),
        'indicatif' => '+229',
        'smsto' => [
            'cle' => env('SMSTO_API_KEY'),
            'expediteur' => env('SMSTO_EXPEDITEUR', 'VIVI'),
            'url' => env('SMSTO_URL', 'https://api.sms.to/sms/send'),
        ],
    ],

    // Paiement des clients
    'paiement' => [
        // « simulation » (local : page de paiement factice, rien n'est débité) ou « fedapay »
        'pilote' => env('VIVI_PAIEMENT_PILOTE', 'simulation'),
        'moyens' => ['MTN Mobile Money', 'Moov Money', 'Celtiis Cash', 'Carte bancaire'],
        'fedapay' => [
            // Clés du tableau de bord FedaPay (Développeurs > Clés API) : sandbox pour les essais, live en production
            'cle_secrete' => env('FEDAPAY_SECRET_KEY'),
            'environnement' => env('FEDAPAY_ENVIRONMENT', 'sandbox'),
            // Clé du webhook (Développeurs > Webhooks), pour vérifier l'en-tête X-FEDAPAY-SIGNATURE
            'cle_webhook' => env('FEDAPAY_WEBHOOK_SECRET'),
        ],
    ],

    // Retraits des propriétaires : modes de règlement proposés à la caissière lors de la validation
    'retraits' => [
        'modes_reglement' => ['MTN Mobile Money', 'Moov Money', 'Celtiis Cash', 'Virement bancaire', 'Espèces'],
    ],

    // Modèle économique des propriétaires (App\Services\ModeleEconomique) : « abonnement » (formules mensuelles)
    // ou « commission » (aucune formule à payer : VIVI garde un pourcentage de chaque retrait). Personnalisable par propriétaire.
    'modele_proprietaires' => [
        'defaut' => 'abonnement',
        'commission_taux' => 10,            // % retenu sur chaque retrait
        'logements_max' => 999,             // quotas sur commission (999 = illimité)
        'salles_max' => 999,
        'gestionnaires_max' => 99,          // 99 = illimité
    ],

    // API de l'application mobile (client, propriétaire, gestionnaire) : /api/v1, jetons Sanctum
    'api' => [
        'jeton_validite_jours' => (int) env('VIVI_API_JETON_JOURS', 90),
        // Adresse ouverte à la fin d'un paiement fait depuis l'application (lien profond Android) :
        // vivi://paiement/{id}?statut=reussi|echoue|en_attente
        'lien_retour_app' => env('VIVI_APP_LIEN_RETOUR', 'vivi://paiement'),
        // Version minimale de l'application acceptée (l'application propose la mise à jour en dessous)
        'version_minimale' => env('VIVI_APP_VERSION_MIN', '1.0.0'),
    ],

    // Devise
    'devise' => 'XOF',
    'devise_libelle' => 'FCFA',
];
