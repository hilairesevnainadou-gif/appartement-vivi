<?php

namespace App\Support;

use App\Models\Parametre;
use Illuminate\Support\Facades\Cache;

/**
 * Paramètres métier de la plateforme.
 *
 * Valeur enregistrée par l'administrateur (page « Paramètres ») si elle existe,
 * sinon valeur par défaut de config/vivi.php. Lecture mise en cache.
 *
 *     Parametres::commissionPourcentage();      // 5.0
 *     Parametres::get('notifications_sms');     // true
 */
class Parametres
{
    private const CLE_CACHE = 'vivi.parametres';

    /** Clé => [chemin dans config/vivi.php, type]. Seules ces clés sont modifiables. */
    public const DEFINITIONS = [
        'commission_pourcentage' => ['vivi.commission_pourcentage', 'float'],
        // Salles de fêtes, de conférence… : le taux dépend du montant (location + services), par tranches
        'commission_salles_tranches' => ['vivi.commission_salles_tranches', 'tranches'],
        'services_beneficiaire' => ['vivi.services_beneficiaire', 'string'],
        'notifications_sms' => ['vivi.notifications.sms', 'bool'],
        'notifications_email' => ['vivi.notifications.email', 'bool'],
        'report_delai_minimum_heures' => ['vivi.report.delai_minimum_heures', 'int'],
        'report_fenetre_jours' => ['vivi.report.fenetre_jours', 'int'],
        'blocage_creneau_minutes' => ['vivi.blocage_creneau_minutes', 'int'],
        'villes' => ['vivi.villes', 'array'],
        'modes_reglement' => ['vivi.retraits.modes_reglement', 'array'],
        'whatsapp_numero' => ['vivi.contact.whatsapp', 'string'],
        'whatsapp_message' => ['vivi.contact.whatsapp_message', 'string'],
        'email_contact' => ['vivi.contact.email', 'string'],
        'essai_actif' => ['vivi.abonnement_promo.essai_actif', 'bool'],
        'essai_jours' => ['vivi.abonnement_promo.essai_jours', 'int'],
        'essai_formule_id' => ['vivi.abonnement_promo.essai_formule_id', 'int'],
        'promo_actif' => ['vivi.abonnement_promo.actif', 'bool'],
        'promo_pourcentage' => ['vivi.abonnement_promo.pourcentage', 'int'],
        'promo_debut' => ['vivi.abonnement_promo.debut', 'date'],
        'promo_fin' => ['vivi.abonnement_promo.fin', 'date'],
        'promo_libelle' => ['vivi.abonnement_promo.libelle', 'string'],
        'promo_nouveaux_seulement' => ['vivi.abonnement_promo.nouveaux_seulement', 'bool'],
        'parrainage_actif' => ['vivi.parrainage.actif', 'bool'],
        'parrainage_bonus_parrain' => ['vivi.parrainage.bonus_parrain', 'int'],
        'parrainage_bonus_filleul' => ['vivi.parrainage.bonus_filleul', 'int'],
        'parrainage_validite_jours' => ['vivi.parrainage.validite_jours', 'int'],
        'bonus_plafond_pourcentage' => ['vivi.parrainage.plafond_pourcentage', 'int'],
        // Publicité dans l'application Android (App\Support\Publicites)
        'pub_active' => ['vivi.publicite.active', 'bool'],
        'pub_frequence_liste' => ['vivi.publicite.frequence_liste', 'int'],
        'pub_plein_ecran_heures' => ['vivi.publicite.plein_ecran_heures', 'int'],
        'pub_emplacements' => ['vivi.publicite.emplacements', 'emplacements'],
        'admob_actif' => ['vivi.publicite.admob.actif', 'bool'],
        'admob_test' => ['vivi.publicite.admob.test', 'bool'],
        'admob_editeur' => ['vivi.publicite.admob.editeur', 'string'],
        // Page /aide/confidentialite : projet visible de l'équipe, ou page publique
        'confidentialite_publiee' => ['vivi.confidentialite_publiee', 'bool'],
    ];

    /**
     * Lien « Nous écrire sur WhatsApp » (wa.me), ou null si aucun numéro n'est réglé.
     * Un numéro béninois sans indicatif reçoit +229 : « 01 97 00 00 00 » → wa.me/2290197000000.
     */
    public static function lienWhatsapp(?string $message = null): ?string
    {
        $chiffres = preg_replace('/\D+/', '', (string) self::get('whatsapp_numero'));
        if ($chiffres === '') {
            return null;
        }
        if (str_starts_with($chiffres, '00')) {
            $chiffres = substr($chiffres, 2);   // 00229… → 229…
        }
        if (! str_starts_with($chiffres, '229') || strlen($chiffres) <= 10) {
            $chiffres = '229'.$chiffres;        // numéro local à 8 ou 10 chiffres
        }
        $texte = $message ?? self::get('whatsapp_message');

        return 'https://wa.me/'.$chiffres.($texte !== '' ? '?text='.rawurlencode($texte) : '');
    }

    public static function get(string $cle): mixed
    {
        if (! array_key_exists($cle, self::DEFINITIONS)) {
            throw new \InvalidArgumentException("Paramètre inconnu : {$cle}");
        }

        [$chemin, $type] = self::DEFINITIONS[$cle];
        $enregistres = Cache::rememberForever(self::CLE_CACHE, fn () => Parametre::pluck('valeur', 'cle')->all());
        $valeur = array_key_exists($cle, $enregistres) && $enregistres[$cle] !== null ? $enregistres[$cle] : config($chemin);

        return self::convertir($valeur, $type);
    }

    /** @return array<string, mixed> toutes les valeurs effectives */
    public static function tous(): array
    {
        $valeurs = [];
        foreach (array_keys(self::DEFINITIONS) as $cle) {
            $valeurs[$cle] = self::get($cle);
        }

        return $valeurs;
    }

    /** @param array<string, mixed> $valeurs */
    public static function enregistrer(array $valeurs): void
    {
        foreach ($valeurs as $cle => $valeur) {
            if (! array_key_exists($cle, self::DEFINITIONS)) {
                continue;
            }
            Parametre::updateOrCreate(['cle' => $cle], ['valeur' => self::convertir($valeur, self::DEFINITIONS[$cle][1])]);
        }
        Cache::forget(self::CLE_CACHE);
    }

    /**
     * Taux de commission en %. Logement : taux unique. Salle (fêtes, conférence, plein air, espace complet) : taux de la
     * tranche où tombe le montant de la réservation (location + services) ; le taux s'applique à tout le montant.
     */
    public static function commissionPourcentage(?\App\Models\Logement $logement = null, int $montant = 0): float
    {
        if ($logement?->estSalle()) {
            return self::tauxSalle($montant);
        }

        return self::get('commission_pourcentage');
    }

    /** Taux de la tranche des salles qui contient ce montant. */
    public static function tauxSalle(int $montant): float
    {
        foreach (self::get('commission_salles_tranches') as $t) {
            if ($t['jusqua'] === null || $montant <= $t['jusqua']) {
                return $t['pourcentage'];
            }
        }

        return self::get('commission_pourcentage');
    }

    /**
     * Tranches nettoyées et triées : [{jusqua: int|null, pourcentage: float}], la dernière sans plafond (« au-delà »).
     *
     * @return list<array{jusqua: ?int, pourcentage: float}>
     */
    private static function tranches(mixed $valeur): array
    {
        $tranches = collect(is_array($valeur) ? $valeur : [])
            ->filter(fn ($t) => is_array($t) && isset($t['pourcentage']) && $t['pourcentage'] !== '')
            ->map(fn ($t) => [
                'jusqua' => isset($t['jusqua']) && $t['jusqua'] !== '' && $t['jusqua'] !== null ? max(0, (int) $t['jusqua']) : null,
                'pourcentage' => min(50, max(0, (float) $t['pourcentage'])),
            ])
            ->sortBy(fn ($t) => $t['jusqua'] ?? PHP_INT_MAX)
            ->values();
        // Une seule tranche « au-delà », toujours en dernier
        $sansPlafond = $tranches->firstWhere('jusqua', null);
        $tranches = $tranches->whereNotNull('jusqua')->values();
        $tranches->push($sansPlafond ?? ['jusqua' => null, 'pourcentage' => $tranches->last()['pourcentage'] ?? (float) config('vivi.commission_pourcentage')]);

        return $tranches->all();
    }

    /**
     * Espaces publicitaires : un réglage par espace, même ceux ajoutés depuis le dernier enregistrement.
     *
     * @return array<string, array{source: string, admob: string}>
     */
    private static function emplacements(mixed $valeur): array
    {
        $valeur = is_array($valeur) ? $valeur : [];
        $liste = [];
        foreach (\App\Enums\EmplacementPub::cases() as $e) {
            $r = is_array($valeur[$e->value] ?? null) ? $valeur[$e->value] : [];
            $source = $r['source'] ?? null;
            $source = $source instanceof \App\Enums\SourcePub ? $source : \App\Enums\SourcePub::tryFrom((string) $source);
            $liste[$e->value] = [
                'source' => ($source ?? \App\Enums\SourcePub::ViviPuisAdmob)->value,
                'admob' => trim((string) ($r['admob'] ?? '')),
            ];
        }

        return $liste;
    }

    /** « jusqu'à 100 000 FCFA : 10 % · jusqu'à 500 000 FCFA : 8 % · au-delà : 6 % » */
    public static function tranchesEnClair(): string
    {
        return collect(self::get('commission_salles_tranches'))->map(fn ($t) => ($t['jusqua'] !== null
            ? 'jusqu’à '.Montant::fcfa($t['jusqua']) : 'au-delà').' : '.rtrim(rtrim(number_format($t['pourcentage'], 2, ',', ''), '0'), ',').' %')->implode(' · ');
    }

    private static function convertir(mixed $valeur, string $type): mixed
    {
        return match ($type) {
            'float' => (float) $valeur,
            'int' => (int) $valeur,
            'bool' => (bool) $valeur,
            'array' => array_values(array_filter((array) $valeur, fn ($v) => $v !== null && $v !== '')),
            'tranches' => self::tranches($valeur),
            'emplacements' => self::emplacements($valeur),
            'date' => blank($valeur) ? '' : \Illuminate\Support\Carbon::parse($valeur)->format('Y-m-d'),
            default => (string) $valeur,
        };
    }
}
