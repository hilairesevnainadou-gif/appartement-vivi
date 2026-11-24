<?php

use App\Http\Controllers\Admin\ExportsController;
use App\Http\Controllers\AideController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\Auth\ConnexionController;
use App\Http\Controllers\Client\ApresVenteController;
use App\Http\Controllers\Client\ParrainageController;
use App\Http\Controllers\Client\ReservationController;
use App\Http\Controllers\Client\ReservationsController;
use App\Http\Controllers\PaiementController;
use App\Http\Controllers\Agent\EspaceAgentController;
use App\Http\Controllers\Proprietaire\AbonnementController;
use App\Http\Controllers\Proprietaire\AgentsController;
use App\Http\Controllers\Proprietaire\CalendrierController;
use App\Http\Controllers\Proprietaire\GestionnairesController;
use App\Http\Controllers\Proprietaire\LogementsController;
use App\Http\Controllers\Proprietaire\RetraitController;
use App\Http\Controllers\Proprietaire\RevenusController;
use App\Http\Controllers\Proprietaire\ScannerController;
use App\Http\Controllers\Proprietaire\TableauDeBordController;
use App\Http\Controllers\Public\AccueilController;
use App\Http\Controllers\Public\LogementController;
use App\Http\Controllers\Public\ProprietairesController;
use App\Http\Controllers\Public\RechercheController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Site public
|--------------------------------------------------------------------------
| L'administration est servie par Filament sur /admin (AdminPanelProvider).
*/

Route::get('/', AccueilController::class)->name('accueil');
Route::get('/logements', RechercheController::class)->name('recherche');
Route::get('/proprietaires', ProprietairesController::class)->name('proprietaires');
// Demandé par Google AdMob sur le site du développeur (identifiant éditeur réglé dans Paramètres)
Route::get('/app-ads.txt', fn () => response(\App\Support\Publicites::appAdsTxt() ?? abort(404), 200, ['Content-Type' => 'text/plain; charset=utf-8']))->name('app-ads');
Route::get('/aide/annulation-et-report', [AideController::class, 'annulation'])->name('aide.annulation');
Route::get('/aide/conditions-generales', [AideController::class, 'conditions'])->name('aide.conditions');
Route::get('/aide/confidentialite', [AideController::class, 'confidentialite'])->name('aide.confidentialite');
Route::get('/aide/supprimer-mon-compte', [AideController::class, 'suppression'])->name('aide.suppression');
// Ancienne carte des salles, regroupée avec celle des logements
Route::redirect('/admin/carte-salles', '/admin/carte?categorie=salle');
Route::get('/logements/{slug}', [LogementController::class, 'show'])->name('logement');
Route::get('/logements/{slug}/disponibilites', [LogementController::class, 'disponibilites'])
    ->middleware('throttle:120,1')
    ->name('logement.disponibilites');

/*
|--------------------------------------------------------------------------
| Connexion par code (email ou SMS)
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->controller(ConnexionController::class)->group(function () {
    Route::get('/connexion', 'formulaire')->name('connexion');
    Route::post('/connexion', 'envoyerCode')->middleware('throttle:envoi-code')->name('connexion.envoyer');
    Route::get('/connexion/code', 'formulaireCode')->name('connexion.code');
    Route::post('/connexion/code', 'verifierCode')->middleware('throttle:verification-code')->name('connexion.verifier');
    Route::post('/connexion/renvoyer', 'renvoyer')->middleware('throttle:envoi-code')->name('connexion.renvoyer');
});

// « Continuer avec Google » : clients seulement
Route::middleware(['guest', 'throttle:20,1'])->controller(\App\Http\Controllers\Auth\GoogleController::class)->group(function () {
    Route::get('/connexion/google', 'redirection')->name('connexion.google');
    Route::get('/connexion/google/retour', 'retour')->name('connexion.google.retour');
});

Route::post('/deconnexion', [ConnexionController::class, 'deconnexion'])->middleware('auth')->name('deconnexion');

/*
|--------------------------------------------------------------------------
| Espaces connectés
|--------------------------------------------------------------------------
| Contenu détaillé : espace client au lot 6, espace propriétaire au lot 5.
*/

Route::middleware('auth')->group(function () {
    Route::get('/espace', fn () => redirect(auth()->user()->urlEspace()))->name('espace');
    Route::get('/mon-compte', ReservationsController::class)->name('espace.client');
    Route::get('/parrainage', ParrainageController::class)->name('parrainage');
    Route::get('/mon-profil', [ProfilController::class, 'index'])->name('profil');
    Route::post('/mon-profil/nom', [ProfilController::class, 'nom'])->name('profil.nom');
    Route::post('/mon-profil/preferences', [ProfilController::class, 'preferences'])->name('profil.preferences');
    Route::post('/mon-profil/changement', [ProfilController::class, 'demander'])->middleware('throttle:envoi-code')->name('profil.demander');
    Route::post('/mon-profil/changement/code', [ProfilController::class, 'confirmer'])->middleware('throttle:verification-code')->name('profil.confirmer');
    Route::post('/mon-profil/changement/annuler', [ProfilController::class, 'annuler'])->name('profil.annuler');
    Route::controller(\App\Http\Controllers\SuppressionCompteController::class)->prefix('/mon-profil/suppression')->group(function () {
        Route::get('/', 'index')->name('profil.suppression');
        Route::post('/code', 'code')->middleware('throttle:envoi-code')->name('profil.suppression.code');
        Route::post('/annuler', 'annuler')->name('profil.suppression.annuler');
        Route::post('/', 'confirmer')->middleware('throttle:verification-code')->name('profil.suppression.confirmer');
    });
    Route::get('/notifications', [NotificationsController::class, 'index'])->name('notifications');
    Route::get('/notifications/{id}', [NotificationsController::class, 'ouvrir'])->name('notifications.ouvrir');
    Route::post('/notifications/tout-lu', [NotificationsController::class, 'toutLu'])->name('notifications.tout-lu');

    // Tunnel de réservation (lot 4)
    Route::post('/reserver/{slug}', [ReservationController::class, 'bloquer'])->middleware('throttle:20,1')->name('reservation.bloquer');
    Route::get('/reservations/{reference}/paiement', [ReservationController::class, 'paiement'])->name('reservation.paiement');
    Route::get('/reservations/{reference}/devis', [ReservationController::class, 'devis'])->middleware('throttle:120,1')->name('reservation.devis');
    Route::post('/reservations/{reference}/payer', [ReservationController::class, 'payer'])->middleware('throttle:10,1')->name('reservation.payer');
    Route::get('/mes-reservations/{reference}', [ReservationController::class, 'voir'])->name('reservation.voir');
    Route::get('/mes-reservations/{reference}/recu', [ReservationController::class, 'recu'])->name('reservation.recu');

    // Après-vente (lot 6) : report jusqu'à 3 h avant, annulation, avis
    Route::controller(ApresVenteController::class)->prefix('/mes-reservations/{reference}')->group(function () {
        Route::get('/reporter', 'formulaireReport')->name('reservation.reporter');
        Route::post('/reporter', 'reporter')->middleware('throttle:10,1')->name('reservation.reporter.confirmer');
        Route::get('/annuler', 'formulaireAnnulation')->name('reservation.annuler');
        Route::post('/annuler', 'annuler')->middleware('throttle:10,1')->name('reservation.annuler.confirmer');
        Route::post('/avis', 'avis')->middleware('throttle:10,1')->name('reservation.avis');
    });

    // Agent marketing : liens d'affiliation, commissions et retraits (il n'accueille pas les clients)
    Route::middleware('role:agent_marketing')->group(function () {
        Route::get('/agent', EspaceAgentController::class)->name('agent');
        Route::post('/agent/retraits', [RetraitController::class, 'store'])->middleware('throttle:6,1')->name('agent.retraits');
    });

    Route::middleware('role:proprietaire|gestionnaire')->group(function () {
        Route::get('/pro', TableauDeBordController::class)->name('espace.proprietaire');

        // Mes logements (propriétaire seulement : le gestionnaire accueille, il ne modifie pas)
        Route::middleware('role:proprietaire')->prefix('pro/logements')->name('pro.logements')->controller(LogementsController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('/nouveau', 'create')->name('.create');
            Route::post('/', 'store')->name('.store');
            Route::get('/{logement}', 'edit')->name('.edit');
            Route::post('/{logement}', 'update')->name('.update');
            Route::patch('/{logement}/en-ligne', 'enLigne')->name('.en-ligne');
            Route::post('/{logement}/photos', 'ajouterPhotos')->name('.photos');
            Route::post('/{logement}/photos/{photo}/supprimer', 'supprimerPhoto')->name('.photos.supprimer');
            Route::post('/{logement}/photos/{photo}/principale', 'photoPrincipale')->name('.photos.principale');
        });

        // Réservations (propriétaire et gestionnaire)
        Route::get('/pro/reservations', [RevenusController::class, 'reservations'])->name('pro.reservations');

        // Abonnement, revenus et gestionnaires (propriétaire seulement)
        Route::middleware('role:proprietaire')->group(function () {
            Route::get('/pro/revenus', [RevenusController::class, 'revenus'])->name('pro.revenus');
            Route::get('/pro/gestionnaires', [GestionnairesController::class, 'index'])->name('pro.gestionnaires');
            Route::post('/pro/gestionnaires', [GestionnairesController::class, 'store'])->middleware('throttle:10,1')->name('pro.gestionnaires.store');
            Route::post('/pro/gestionnaires/{gestionnaire}', [GestionnairesController::class, 'update'])->name('pro.gestionnaires.update');
            Route::get('/pro/agents', [AgentsController::class, 'index'])->name('pro.agents');
            Route::post('/pro/agents', [AgentsController::class, 'store'])->middleware('throttle:10,1')->name('pro.agents.store');
            Route::post('/pro/agents/{agent}', [AgentsController::class, 'update'])->name('pro.agents.update');
            Route::get('/pro/abonnement', [AbonnementController::class, 'index'])->name('pro.abonnement');
            Route::post('/pro/abonnement/{formule}', [AbonnementController::class, 'souscrire'])->middleware('throttle:10,1')->name('pro.abonnement.souscrire');
            Route::post('/pro/abonnement/{formule}/essai', [AbonnementController::class, 'essai'])->middleware('throttle:10,1')->name('pro.abonnement.essai');
        });

        // Calendrier : jours bloqués et déplacement d'une réservation (propriétaire et gestionnaire)
        Route::get('/pro/calendrier', [CalendrierController::class, 'index'])->name('pro.calendrier');
        Route::post('/pro/calendrier/{logement}/blocages', [CalendrierController::class, 'bloquer'])->name('pro.calendrier.bloquer');
        Route::delete('/pro/calendrier/{logement}/blocages/{blocage}', [CalendrierController::class, 'debloquer'])->name('pro.calendrier.debloquer');
        Route::get('/pro/reservations/{reservation:reference}/deplacement', [CalendrierController::class, 'optionsDeplacement'])->name('pro.calendrier.deplacement');
        Route::post('/pro/reservations/{reservation:reference}/deplacer', [CalendrierController::class, 'deplacer'])->name('pro.calendrier.deplacer');
        Route::post('/pro/calendrier/{logement}/reservations', [CalendrierController::class, 'reserver'])->middleware('throttle:30,1')->name('pro.calendrier.reserver');
        Route::post('/pro/reservations/{reservation:reference}/retirer', [CalendrierController::class, 'retirer'])->name('pro.calendrier.retirer');
        Route::post('/pro/reservations/{reservation:reference}/encaisser', [CalendrierController::class, 'encaisser'])->name('pro.calendrier.encaisser');

        // Accueil du client : QR Code ou code de secours (essais limités pour empêcher de deviner un code)
        Route::get('/pro/scanner', [ScannerController::class, 'index'])->name('pro.scanner');
        Route::post('/pro/scanner/verifier', [ScannerController::class, 'verifier'])->middleware('throttle:20,1')->name('pro.scanner.verifier');
        Route::post('/pro/scanner/demarrer', [ScannerController::class, 'demarrer'])->middleware('throttle:20,1')->name('pro.scanner.demarrer');
        Route::post('/pro/retraits', [RetraitController::class, 'store'])
            ->middleware(['role:proprietaire', 'throttle:6,1'])
            ->name('pro.retraits.demander');
    });
});

/*
|--------------------------------------------------------------------------
| Paiement
|--------------------------------------------------------------------------
| Le statut est toujours relu chez le prestataire : ces adresses ne font confiance à aucun paramètre reçu.
*/

Route::get('/paiements/{paiement}/retour', [PaiementController::class, 'retour'])->middleware('throttle:30,1')->name('paiement.retour');
// Paiement lancé depuis l'application mobile : statut relu, puis retour dans l'application (lien profond)
Route::get('/paiements/{paiement}/retour-app', [\App\Http\Controllers\Api\V1\PaiementsController::class, 'retourApp'])->middleware('throttle:30,1')->name('paiement.retour-app');
Route::post('/webhooks/fedapay', [PaiementController::class, 'webhook'])->middleware('throttle:120,1')->name('paiement.webhook');
Route::get('/paiements/{paiement}/simulation', [PaiementController::class, 'simulation'])->middleware('signed')->name('paiement.simulation');
Route::post('/paiements/{paiement}/simulation', [PaiementController::class, 'simuler'])->name('paiement.simuler');

/*
|--------------------------------------------------------------------------
| Exports comptables (page Filament « Exports comptables », administrateur et caissière)
|--------------------------------------------------------------------------
*/

Route::get('/admin-exports/{type}', ExportsController::class)->middleware(['auth', 'throttle:20,1'])->name('admin.exports');
