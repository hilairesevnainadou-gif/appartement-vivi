<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Salles de fêtes et de conférence : équipements et services à la carte qui leur sont propres.
 * Ajoutés seulement s'ils n'existent pas (même liste que Database\Seeders\CatalogueSeeder).
 */
return new class extends Migration
{
    public const EQUIPEMENTS = [
        ['Sonorisation', 'bi-speaker'], ['Micro', 'bi-mic'], ['Vidéoprojecteur et écran', 'bi-projector'],
        ['Tables et chaises', 'bi-grid-3x3'], ['Scène ou estrade', 'bi-easel'], ['Espace extérieur', 'bi-tree'],
        ['Accès personnes à mobilité réduite', 'bi-person-wheelchair'],
    ];

    public const SERVICES = [
        ['Décoration de salle', 'Mise en place selon le thème de votre événement.', 50000, 'la prestation', 'bi-balloon'],
        ['Traiteur', 'Repas et boissons servis sur place.', 5000, 'par personne', 'bi-basket'],
        ['Agent de sécurité', 'Un agent pendant toute la durée de l’événement.', 15000, 'par agent', 'bi-shield-check'],
        ['Nettoyage après l’événement', 'La salle est remise en état après votre départ.', 20000, 'la prestation', 'bi-stars'],
    ];

    public function up(): void
    {
        $ordre = (int) DB::table('equipements')->max('ordre');
        foreach (self::EQUIPEMENTS as [$nom, $icone]) {
            if (! DB::table('equipements')->where('nom', $nom)->exists()) {
                DB::table('equipements')->insert(['nom' => $nom, 'icone' => $icone, 'ordre' => ++$ordre, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        $ordre = (int) DB::table('services')->max('ordre');
        foreach (self::SERVICES as [$nom, $description, $prix, $unite, $icone]) {
            if (! DB::table('services')->where('nom', $nom)->exists()) {
                DB::table('services')->insert([
                    'nom' => $nom, 'description' => $description, 'prix' => $prix, 'unite' => $unite, 'icone' => $icone,
                    'ordre' => ++$ordre, 'actif' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('equipements')->whereIn('nom', array_column(self::EQUIPEMENTS, 0))->delete();
        DB::table('services')->whereIn('nom', array_column(self::SERVICES, 0))->delete();
    }
};
