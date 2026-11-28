<?php

namespace Database\Seeders;

use App\Models\Logement;
use Illuminate\Database\Seeder;

/**
 * Seed de PRODUCTION : seulement ce qu'il faut pour démarrer, sans aucun logement, salle, client ni réservation.
 *   - rôles et permissions de l'administration ;
 *   - premier administrateur (ADMIN_EMAIL / ADMIN_PASSWORD du .env) ;
 *   - catalogue : équipements, services à la carte, formules d'abonnement (modifiables ensuite).
 * Les équipements et services des salles sont créés par les migrations.
 *
 *     php artisan migrate --force
 *     php artisan db:seed --class=ProductionSeeder --force
 *
 * Peut être relancé sans risque : rien n'est créé en double, rien n'est supprimé.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction() && ! env('ADMIN_EMAIL')) {
            $this->command?->error('Renseignez ADMIN_EMAIL (et ADMIN_PASSWORD) dans le .env avant de lancer ce seed.');

            return;
        }

        $this->call([
            RoleSeeder::class,
            AdministrateurSeeder::class,
            CatalogueSeeder::class,
        ]);

        $demo = Logement::where('slug', 'studio-lagune')->exists();
        $this->command?->info('Seed de production terminé : rôles, administrateur et catalogue. Aucun logement ni salle créé.');
        if ($demo) {
            $this->command?->warn('Attention : des logements de démonstration sont présents dans cette base (ex. « Studio Lagune »).');
        }
    }
}
