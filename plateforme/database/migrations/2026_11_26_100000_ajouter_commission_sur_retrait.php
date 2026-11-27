<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modèle économique des propriétaires : abonnement (formules) ou commission prélevée sur chaque retrait.
 *  - users.modele_economique : null = règle générale (Paramètres), 'abonnement' ou 'commission' = choix personnalisé.
 *  - users.commission_retrait_taux : taux personnalisé en %, null = taux général.
 *  - retraits : part de VIVI figée au moment de la demande, et montant net versé au propriétaire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('modele_economique', 20)->nullable()->after('actif');
            $table->decimal('commission_retrait_taux', 5, 2)->nullable()->after('modele_economique');
        });

        Schema::table('retraits', function (Blueprint $table) {
            $table->decimal('taux_commission', 5, 2)->default(0)->after('montant');
            $table->unsignedBigInteger('montant_commission')->default(0)->after('taux_commission');
            $table->unsignedBigInteger('montant_net')->nullable()->after('montant_commission');
        });
    }

    public function down(): void
    {
        Schema::table('retraits', fn (Blueprint $table) => $table->dropColumn(['taux_commission', 'montant_commission', 'montant_net']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['modele_economique', 'commission_retrait_taux']));
    }
};
