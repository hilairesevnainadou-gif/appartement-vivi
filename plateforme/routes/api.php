<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Client\ReservationsController;
use App\Http\Controllers\Api\V1\CompteController;
use App\Http\Controllers\Api\V1\Finances\CompteRetraitController;
use App\Http\Controllers\Api\V1\ConfigurationController;
use App\Http\Controllers\Api\V1\LogementsController;
use App\Http\Controllers\Api\V1\PaiementsController;
use App\Http\Controllers\Api\V1\PublicitesController;
use App\Http\Controllers\Api\V1\SuppressionCompteController;
use App\Http\Controllers\Api\V1\Pro\AccueilController;
use App\Http\Controllers\Api\V1\Pro\CalendrierController;
use App\Http\Controllers\Api\V1\Pro\FinancesController;
use App\Http\Controllers\Api\V1\Pro\LogementsController as ProLogementsController;
use App\Http\Controllers\Api\V1\Agent\EspaceController as AgentEspaceController;
use App\Http\Controllers\Api\V1\Pro\AgentsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API de l'application mobile VIVI (clients, propriétaires, gestionnaires)
|--------------------------------------------------------------------------
| Préfixe /api/v1. Authentification : en-tête « Authorization: Bearer {jeton} » obtenu par
| POST /auth/code puis POST /auth/verifier. Réponses en JSON, messages en français.
| Documentation : plateforme/docs/API-mobile.md
*/

Route::prefix('v1')->name('api.')->middleware('throttle:api')->group(function () {

    /* ---------- Sans connexion ---------- */

    Route::get('/configuration', ConfigurationController::class)->name('configuration');

    // Espaces publicitaires de l'application
    Route::get('/publicites', [PublicitesController::class, 'index'])->name('publicites.index');
    Route::post('/publicites/{publicite}/vue', [PublicitesController::class, 'vue'])->whereNumber('publicite')->name('publicites.vue');
    Route::post('/publicites/{publicite}/clic', [PublicitesController::class, 'clic'])->whereNumber('publicite')->name('publicites.clic');

    Route::prefix('auth')->name('auth.')->controller(AuthController::class)->group(function () {
        Route::post('/code', 'demanderCode')->middleware('throttle:envoi-code')->name('code');
        Route::post('/renvoyer', 'renvoyer')->middleware('throttle:envoi-code')->name('renvoyer');
        Route::post('/verifier', 'verifier')->middleware('throttle:verification-code')->name('verifier');
        Route::post('/google', 'google')->middleware('throttle:verification-code')->name('google');   // clients seulement
    });

    Route::get('/logements', [LogementsController::class, 'index'])->name('logements.index');
    Route::get('/logements/{slug}', [LogementsController::class, 'show'])->name('logements.show');
    Route::get('/logements/{slug}/disponibilites', [LogementsController::class, 'disponibilites'])->name('logements.disponibilites');
    Route::get('/logements/{slug}/avis', [LogementsController::class, 'avis'])->name('logements.avis');

    /* ---------- Connecté (tous les rôles) ---------- */

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/moi', [AuthController::class, 'moi'])->name('moi');
        Route::post('/auth/deconnexion', [AuthController::class, 'deconnexion'])->name('auth.deconnexion');
        Route::post('/auth/deconnexion-partout', [AuthController::class, 'deconnexionPartout'])->name('auth.deconnexion-partout');

        Route::controller(CompteController::class)->group(function () {
            Route::get('/profil', 'profil')->name('profil');
            Route::put('/profil/nom', 'nom')->name('profil.nom');
            Route::post('/profil/changement', 'demanderChangement')->middleware('throttle:envoi-code')->name('profil.changement');
            Route::post('/profil/changement/confirmer', 'confirmerChangement')->middleware('throttle:verification-code')->name('profil.changement.confirmer');
            Route::get('/notifications', 'notifications')->name('notifications');
            Route::post('/notifications/tout-lu', 'toutLire')->name('notifications.tout-lu');
            Route::get('/notifications/compteur', 'compteurNotifications')->name('notifications.compteur');
            Route::get('/notifications/{id}', 'notification')->name('notifications.show');
            Route::post('/notifications/{id}/lu', 'lireNotification')->name('notifications.lu');
            Route::delete('/notifications/{id}', 'supprimerNotification')->name('notifications.supprimer');
            Route::put('/profil/preferences', 'preferences')->name('profil.preferences');
            Route::post('/appareils', 'enregistrerAppareil')->middleware('throttle:30,1')->name('appareils.enregistrer');
            Route::delete('/appareils', 'retirerAppareil')->name('appareils.retirer');
            Route::get('/parrainage', 'parrainage')->name('parrainage');
        });

        Route::controller(SuppressionCompteController::class)->prefix('compte/suppression')->group(function () {
            Route::get('/', 'show')->name('compte.suppression');
            Route::post('/code', 'code')->middleware('throttle:envoi-code')->name('compte.suppression.code');
            Route::post('/', 'confirmer')->middleware('throttle:verification-code')->name('compte.suppression.confirmer');
        });

        Route::get('/paiements/{paiement}', [PaiementsController::class, 'show'])->middleware('throttle:60,1')->name('paiements.show');

        // Client : réservations, billet, report, annulation, avis
        Route::post('/logements/{slug}/reserver', [ReservationsController::class, 'bloquer'])->middleware('throttle:20,1')->name('reservations.bloquer');
        Route::controller(ReservationsController::class)->prefix('reservations')->name('reservations.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{reference}', 'show')->name('show');
            Route::post('/{reference}/devis', 'devis')->middleware('throttle:120,1')->name('devis');
            Route::post('/{reference}/payer', 'payer')->middleware('throttle:10,1')->name('payer');
            Route::get('/{reference}/report', 'optionsReport')->name('report');
            Route::post('/{reference}/report', 'reporter')->middleware('throttle:10,1')->name('reporter');
            Route::get('/{reference}/annulation', 'simulationAnnulation')->name('annulation');
            Route::post('/{reference}/annuler', 'annuler')->middleware('throttle:10,1')->name('annuler');
            Route::post('/{reference}/avis', 'avis')->middleware('throttle:10,1')->name('avis');
        });

        /* ---------- Compte de retrait (propriétaire et agent marketing) ---------- */

        Route::middleware('role:proprietaire|agent_marketing')->controller(CompteRetraitController::class)->group(function () {
            Route::get('/compte-retrait', 'show')->name('compte-retrait');
            Route::put('/compte-retrait', 'update')->name('compte-retrait.update');
        });

        /* ---------- Agent marketing (liens d'affiliation, commissions, retraits) ---------- */

        Route::middleware('role:agent_marketing')->prefix('agent')->name('agent.')->group(function () {
            Route::get('/', AgentEspaceController::class)->name('espace');
            Route::post('/retraits', [FinancesController::class, 'demanderRetrait'])->middleware('throttle:6,1')->name('retraits.store');
        });

        /* ---------- Propriétaire et gestionnaire ---------- */

        Route::middleware('role:proprietaire|gestionnaire')->prefix('pro')->name('pro.')->group(function () {
            Route::get('/tableau-de-bord', [AccueilController::class, 'tableauDeBord'])->name('tableau-de-bord');
            Route::get('/arrivees', [AccueilController::class, 'attendus'])->name('arrivees');
            Route::post('/scanner/verifier', [AccueilController::class, 'verifier'])->middleware('throttle:20,1')->name('scanner.verifier');
            Route::post('/scanner/demarrer', [AccueilController::class, 'demarrer'])->middleware('throttle:20,1')->name('scanner.demarrer');

            Route::controller(CalendrierController::class)->group(function () {
                Route::get('/logements-geres', 'logements')->name('logements-geres');
                Route::get('/reservations', 'reservations')->name('reservations');
                Route::get('/reservations/{reservation:reference}', 'reservation')->name('reservations.show');
                Route::post('/reservations/{reservation:reference}/deplacer', 'deplacer')->name('reservations.deplacer');
                Route::post('/reservations/{reservation:reference}/encaisser', 'encaisser')->name('reservations.encaisser');
                Route::post('/reservations/{reservation:reference}/retirer', 'retirer')->name('reservations.retirer');
                Route::get('/logements/{logement}/calendrier', 'calendrier')->name('calendrier');
                Route::post('/logements/{logement}/blocages', 'bloquer')->name('blocages.store');
                Route::delete('/logements/{logement}/blocages/{blocage}', 'debloquer')->name('blocages.destroy');
                Route::post('/logements/{logement}/reservations', 'ajouter')->middleware('throttle:30,1')->name('reservations.ajouter');
            });

            /* ---------- Propriétaire seulement ---------- */

            Route::middleware('role:proprietaire')->group(function () {
                Route::controller(ProLogementsController::class)->prefix('logements')->name('logements.')->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{logement}', 'show')->name('show');
                    Route::put('/{logement}', 'update')->name('update');
                    Route::patch('/{logement}/en-ligne', 'enLigne')->name('en-ligne');
                    Route::post('/{logement}/photos', 'ajouterPhotos')->name('photos.store');
                    Route::delete('/{logement}/photos/{photo}', 'supprimerPhoto')->name('photos.destroy');
                    Route::post('/{logement}/photos/{photo}/principale', 'photoPrincipale')->name('photos.principale');
                });

                Route::controller(FinancesController::class)->group(function () {
                    Route::get('/revenus', 'revenus')->name('revenus');
                    Route::post('/retraits', 'demanderRetrait')->middleware('throttle:6,1')->name('retraits.store');
                    Route::get('/abonnement', 'abonnement')->name('abonnement');
                    Route::post('/abonnement/{formule}', 'souscrire')->middleware('throttle:10,1')->name('abonnement.souscrire');
                    Route::post('/abonnement/{formule}/essai', 'essai')->middleware('throttle:10,1')->name('abonnement.essai');
                    Route::get('/gestionnaires', 'gestionnaires')->name('gestionnaires');
                    Route::post('/gestionnaires', 'confier')->middleware('throttle:10,1')->name('gestionnaires.store');
                    Route::put('/gestionnaires/{gestionnaire}', 'modifierGestionnaire')->name('gestionnaires.update');
                });

                Route::controller(AgentsController::class)->prefix('agents')->name('agents.')->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->middleware('throttle:10,1')->name('store');
                    Route::put('/{agent}', 'update')->name('update');
                });
            });
        });
    });
});
