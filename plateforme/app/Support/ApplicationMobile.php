<?php

namespace App\Support;

/**
 * Liens de téléchargement de l'application VIVI affichés sur le site (accueil et pied de page).
 * Réglés dans Administration › Paramètres › « Application mobile ». Lien vide : « Bientôt disponible ».
 * APK Android : lien saisi, sinon le fichier public/telechargements/vivi.apk s'il a été déposé sur le serveur.
 */
class ApplicationMobile
{
    /** Emplacement du fichier APK déposé sur le serveur (relatif à public/). */
    public const FICHIER_APK = 'telechargements/vivi.apk';

    public static function affichee(): bool
    {
        return (bool) Parametres::get('app_afficher');
    }

    /**
     * Les trois boutons, dans l'ordre d'affichage. « lien » null : pas encore disponible.
     *
     * @return list<array{cle: string, icone: string, haut: string, nom: string, lien: ?string, externe: bool, detail: ?string}>
     */
    public static function boutons(): array
    {
        $apk = self::lienApk();
        $play = self::lien('app_play_store');
        $apple = self::lien('app_app_store');
        $version = trim((string) Parametres::get('app_apk_version'));

        return [
            [
                'cle' => 'apk', 'icone' => 'bi-android2', 'nom' => 'APK Android',
                'haut' => $apk ? 'Télécharger' : 'Bientôt disponible',
                'lien' => $apk, 'externe' => $apk !== null && ! str_starts_with($apk, url('/')),
                'detail' => $apk ? ($version !== '' ? '(version '.$version.') ' : '').'disponible sur le site' : null,
            ],
            [
                'cle' => 'play', 'icone' => 'bi-google-play', 'nom' => 'Google Play',
                'haut' => $play ? 'Disponible sur' : 'Bientôt disponible',
                'lien' => $play, 'externe' => true, 'detail' => null,
            ],
            [
                'cle' => 'apple', 'icone' => 'bi-apple', 'nom' => 'App Store',
                'haut' => $apple ? 'Télécharger dans' : 'Bientôt disponible',
                'lien' => $apple, 'externe' => true, 'detail' => null,
            ],
        ];
    }

    /** Au moins un bouton actif (sinon, on n'affiche que « Bientôt disponible »). */
    public static function disponible(): bool
    {
        return collect(self::boutons())->contains(fn ($b) => $b['lien'] !== null);
    }

    public static function lienApk(): ?string
    {
        if ($lien = self::lien('app_apk_lien')) {
            return $lien;
        }

        return is_file(public_path(self::FICHIER_APK)) ? asset(self::FICHIER_APK) : null;
    }

    /** Lien saisi : adresse complète http(s), ou chemin du site (/telechargements/…). */
    private static function lien(string $cle): ?string
    {
        $valeur = trim((string) Parametres::get($cle));
        if ($valeur === '') {
            return null;
        }
        if (str_starts_with($valeur, '/')) {
            return url($valeur);
        }

        return preg_match('#^https?://#i', $valeur) ? $valeur : null;
    }
}
