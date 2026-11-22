<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Toujours : rôles, premier administrateur et catalogue de départ.
     * En local seulement : données de démonstration (logements, comptes, réservations).
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            AdministrateurSeeder::class,
            CatalogueSeeder::class,
        ]);

        if (app()->environment('local')) {
            $this->call(DemoSeeder::class);
        }
    }
}
