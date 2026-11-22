<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rémunération de VIVI pour la gestion confiée : pourcentage de la location ou prix fixe par réservation,
 * selon le contrat signé avec le propriétaire. La règle et le montant sont figés dans chaque réservation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logements', function (Blueprint $table) {
            $table->string('gestion_type', 20)->nullable()->after('gestion_vivi_demandee_le');
            $table->decimal('gestion_valeur', 12, 2)->default(0)->after('gestion_type');
            $table->string('gestion_contrat', 60)->nullable()->after('gestion_valeur');
            $table->date('gestion_contrat_le')->nullable()->after('gestion_contrat');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->string('gestion_type', 20)->nullable()->after('remise_agent');
            $table->decimal('gestion_valeur', 12, 2)->default(0)->after('gestion_type');
            $table->unsignedInteger('montant_gestion')->default(0)->after('gestion_valeur');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', fn (Blueprint $table) => $table->dropColumn(['gestion_type', 'gestion_valeur', 'montant_gestion']));
        Schema::table('logements', fn (Blueprint $table) => $table->dropColumn(['gestion_type', 'gestion_valeur', 'gestion_contrat', 'gestion_contrat_le']));
    }
};
