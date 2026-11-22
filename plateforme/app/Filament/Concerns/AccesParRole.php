<?php

namespace App\Filament\Concerns;

use App\Enums\Role;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Droits d'une ressource Filament, par permissions (Administration › Rôles et permissions).
 *
 * Chaque ressource a deux permissions : « {slug}.voir » (liste, fiche) et « {slug}.gerer » (créer,
 * modifier, supprimer, actions). L'administrateur a toujours tout. Toutes les vérifications de Filament
 * (menu, pages, boutons) passent par getAuthorizationResponse() : c'est le seul point à contrôler.
 *
 * Dans la ressource, les rôles prévus servent de permissions de départ (App\Support\Permissions) :
 *     use AccesParRole;
 *     protected static array $rolesLecture = [Role::Administrateur, Role::Caissiere];   // « voir » au départ
 *     protected static array $rolesEcriture = [Role::Administrateur];                  // « gérer » au départ
 *     protected static array $actionsInterdites = ['create', 'update'];                // pour tout le monde
 */
trait AccesParRole
{
    private const ACTIONS_LECTURE = ['viewAny', 'view'];

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $action = $action instanceof UnitEnum ? $action->name : $action;

        if (in_array($action, static::$actionsInterdites ?? [], true)) {
            return Response::deny('Action non disponible sur cet écran.');
        }

        $autorise = in_array($action, self::ACTIONS_LECTURE, true) ? static::peutLire() : static::peutEcrire();

        return $autorise ? Response::allow() : Response::deny('Vos permissions ne donnent pas accès à cette action.');
    }

    protected static function peutLire(): bool
    {
        return static::autorise(static::getSlug().'.voir') || static::peutEcrire();
    }

    protected static function peutEcrire(): bool
    {
        return static::autorise(static::getSlug().'.gerer');
    }

    /** @return list<Role> */
    public static function rolesLecturePrevus(): array
    {
        return static::$rolesLecture ?? [Role::Administrateur];
    }

    /** @return list<Role> */
    public static function rolesEcriturePrevus(): array
    {
        return static::$rolesEcriture ?? [Role::Administrateur];
    }

    private static function autorise(string $permission): bool
    {
        // can() passe par Spatie Permission ; l'administrateur est autorisé d'office (Gate::before)
        return (bool) auth()->user()?->can($permission);
    }
}
