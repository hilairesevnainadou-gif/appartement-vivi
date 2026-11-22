<?php

namespace App\Support;

use App\Enums\Role;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as RoleSpatie;
use Spatie\Permission\PermissionRegistrar;

/**
 * Catalogue des permissions de l'administration.
 *
 *  - Chaque écran Filament (ressource) a deux permissions : « {slug}.voir » et « {slug}.gerer ».
 *  - Quelques permissions particulières : accès à l'administration, paramètres, exports, rôles, journal.
 *  - L'administrateur a toujours tout (Gate::before dans AppServiceProvider) : on ne peut pas se bloquer dehors.
 *  - Les autres rôles (caissière, rôles créés par l'administrateur) ont les permissions cochées dans
 *    Administration › Rôles et permissions. Valeurs de départ : les rôles prévus dans chaque ressource.
 */
class Permissions
{
    public const ACCES_ADMINISTRATION = 'administration.acceder';

    /** Permissions qui ne correspondent pas à un écran de liste. Nom => [libellé, groupe]. */
    public const PARTICULIERES = [
        self::ACCES_ADMINISTRATION => ['Se connecter à l’administration', 'Général'],
        'parametres.gerer' => ['Modifier les paramètres de la plateforme', 'Administration'],
        'exports.telecharger' => ['Télécharger les exports comptables', 'Finances'],
        'publicites.encaisser' => ['Encaisser les publicités des annonceurs', 'Finances'],
        'carte.voir' => ['Voir la carte des logements et la progression', 'Logements'],
        'documentation.voir' => ['Lire la documentation technique (API mobile, plateforme)', 'Administration'],
    ];

    /** Rôles du site public : ils ne servent pas dans l'administration. */
    public const ROLES_DU_SITE = [Role::Client, Role::Proprietaire, Role::Gestionnaire, Role::AgentMarketing];

    /**
     * Toutes les permissions, avec leur libellé et leur groupe (pour l'écran des rôles).
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function catalogue(): array
    {
        $liste = self::PARTICULIERES;
        foreach (self::ressources() as $ressource) {
            $nom = $ressource::getPluralModelLabel();
            $groupe = (string) ($ressource::getNavigationGroup() ?? 'Général');
            $liste[$ressource::getSlug().'.voir'] = ['Voir : '.mb_strtolower($nom), $groupe];
            $liste[$ressource::getSlug().'.gerer'] = ['Gérer : '.mb_strtolower($nom), $groupe];
        }
        uasort($liste, fn ($a, $b) => [$a[1], $a[0]] <=> [$b[1], $b[0]]);

        return $liste;
    }

    public static function libelle(string $permission): string
    {
        return self::catalogue()[$permission][0] ?? $permission;
    }

    /** Crée les permissions manquantes et donne aux rôles prévus leurs permissions de départ (sans retirer celles ajoutées depuis). */
    public static function synchroniser(): void
    {
        $registre = app(PermissionRegistrar::class);
        $registre->forgetCachedPermissions();
        foreach (array_keys(self::catalogue()) as $nom) {
            Permission::findOrCreate($nom, 'web');
        }

        foreach (self::parDefaut() as $role => $permissions) {
            RoleSpatie::findOrCreate($role, 'web')->givePermissionTo($permissions);
        }
        $registre->forgetCachedPermissions();
    }

    /** @return array<string, list<string>> rôle => permissions de départ */
    public static function parDefaut(): array
    {
        $roles = [
            Role::Administrateur->value => array_keys(self::catalogue()),
            Role::Caissiere->value => [self::ACCES_ADMINISTRATION, 'exports.telecharger', 'publicites.encaisser'],
            // Technicien (développeur de l'application, maintenance) : documentation, alertes et journal, sans les finances
            Role::Technicien->value => [self::ACCES_ADMINISTRATION, 'documentation.voir', 'alertes.voir', 'journal.voir'],
        ];
        foreach (self::ressources() as $ressource) {
            foreach ([Role::Caissiere] as $role) {
                if (in_array($role, $ressource::rolesLecturePrevus(), true)) {
                    $roles[$role->value][] = $ressource::getSlug().'.voir';
                }
                if (in_array($role, $ressource::rolesEcriturePrevus(), true)) {
                    $roles[$role->value][] = $ressource::getSlug().'.gerer';
                }
            }
        }

        return $roles;
    }

    /** @return list<class-string> ressources de l'administration soumises aux permissions */
    private static function ressources(): array
    {
        return array_values(array_filter(
            Filament::getPanel('admin')->getResources(),
            fn (string $r) => method_exists($r, 'rolesLecturePrevus'),
        ));
    }
}
