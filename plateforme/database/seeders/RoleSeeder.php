<?php

namespace Database\Seeders;

use App\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role as RoleSpatie;
use Spatie\Permission\PermissionRegistrar;

/** Crée les rôles de la phase 1. Peut être relancé sans risque. */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Role::cases() as $role) {
            RoleSpatie::findOrCreate($role->value, 'web');
        }

        // Permissions de l'administration et valeurs de départ des rôles (sans retirer celles ajoutées depuis)
        \App\Support\Permissions::synchroniser();
    }
}
