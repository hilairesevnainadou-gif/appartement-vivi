<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Formule;
use App\Enums\TypeLogement;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Format;
use App\Models\Equipement;
use App\Models\Service;
use App\Services\Parrainage;
use App\Support\Parametres;
use Illuminate\Http\JsonResponse;

/**
 * Ce dont l'application a besoin au démarrage, sans être connectée : listes des filtres,
 * réglages publics fixés par l'administrateur (Administration › Paramètres), contact, version minimale.
 */
class ConfigurationController extends Controller
{
    public function __invoke(Parrainage $parrainage): JsonResponse
    {
        return response()->json(['data' => [
            'version_minimale' => (string) config('vivi.api.version_minimale'),
            'devise' => config('vivi.devise_libelle'),
            'villes' => Parametres::get('villes'),
            'formules' => collect(Formule::cases())->map(fn (Formule $f) => Format::enum($f) + [
                'court' => $f->court(), 'unite' => $f->unite(),
                'budget' => array_combine(['min', 'max', 'pas'], $f->budget()),
                'budget_salle' => array_combine(['min', 'max', 'pas'], $f->budget(salle: true)),   // filtre « Budget » quand categorie=salle
            ]),
            // Types avec leur catégorie (logement ou salle) et la capacité maximale saisissable
            'types_logement' => collect(TypeLogement::cases())->map(fn ($t) => Format::enum($t) + ['categorie' => $t->categorie(), 'capacite_max' => $t->capaciteMax()]),
            // Gestion d'une fiche : le propriétaire accueille, ou il confie la gestion à VIVI
            'modes_gestion' => collect(\App\Enums\ModeGestion::cases())->map(fn ($m) => Format::enum($m) + ['choix' => $m->choix(), 'aide' => $m->aide()]),
            'categories' => collect(TypeLogement::CATEGORIES)->map(fn ($libelle, $valeur) => ['valeur' => $valeur, 'libelle' => $libelle])->values(),
            'equipements' => Equipement::orderBy('ordre')->orderBy('nom')->get(['id', 'nom', 'icone']),
            'services' => Service::where('actif', true)->orderBy('ordre')->get(['id', 'nom', 'description', 'prix', 'unite', 'icone']),
            'connexion' => [
                'email' => (bool) Parametres::get('notifications_email'),
                'sms' => (bool) Parametres::smsActif(),
                // Bouton « Continuer avec Google » (clients seulement) ; identifiant client Web à donner à Google Sign-In
                'google' => app(\App\Services\ConnexionGoogle::class)->actif(),
                'google_client_id' => app(\App\Services\ConnexionGoogle::class)->actif() ? config('services.google.client_id') : null,
            ],
            // Notifications push (Firebase) : si vrai, l'application envoie son jeton FCM avec POST /appareils
            'push' => app(\App\Services\NotificationsPush::class)->actif(),
            // Publicité : si vrai, l'application appelle GET /publicites
            'publicite' => \App\Support\Publicites::actives(),
            'commission_pourcentage' => Parametres::commissionPourcentage(),   // logements
            // Salles : taux selon le montant (location + services) ; jusqua = null pour la dernière tranche
            'commission_salles_tranches' => Parametres::get('commission_salles_tranches'),
            'report_delai_minimum_heures' => (int) Parametres::get('report_delai_minimum_heures'),
            'report_fenetre_jours' => (int) Parametres::get('report_fenetre_jours'),
            'blocage_creneau_minutes' => (int) Parametres::get('blocage_creneau_minutes'),
            'parrainage' => [
                'actif' => $parrainage->actif(),
                'bonus_filleul' => (int) Parametres::get('parrainage_bonus_filleul'),
            ],
            'moyens_paiement' => config('vivi.paiement.moyens'),
            'contact' => [
                'whatsapp' => Parametres::lienWhatsapp(),
                'email' => Parametres::get('email_contact') ?: null,
            ],
            'pages' => [
                'conditions' => route('aide.conditions'),
                'annulation' => route('aide.annulation'),
                'suppression_compte' => route('aide.suppression'),
                'confidentialite' => Parametres::get('confidentialite_publiee') ? route('aide.confidentialite') : null,
            ],
        ]]);
    }
}
