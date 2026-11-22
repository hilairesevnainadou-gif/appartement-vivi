<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parrainage (code d'invitation, bonus déductible d'une réservation) et
 * promotions sur les abonnements (essai gratuit, remise sur une période).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('code_parrainage', 16)->nullable()->unique()->after('actif');
            $table->foreignId('parrain_id')->nullable()->after('code_parrainage')->constrained('users')->nullOnDelete();
            // Le parrain n'est récompensé qu'une fois par filleul : au premier séjour démarré
            $table->timestamp('parrainage_recompense_le')->nullable()->after('parrain_id');
        });

        // Bonus en FCFA, utilisables comme réduction sur une réservation jusqu'à leur date d'expiration
        Schema::create('bonus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('motif', 20);                 // parrainage, bienvenue, geste
            $table->string('libelle');
            $table->unsignedInteger('montant');
            $table->unsignedInteger('restant');
            $table->date('expire_le');
            $table->foreignId('filleul_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'expire_le']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            // Part du total réglée avec le bonus (le reste est payé par FedaPay) ; détail par ligne de bonus
            $table->unsignedInteger('montant_bonus')->default(0)->after('montant_total');
            $table->json('bonus_utilises')->nullable()->after('montant_bonus');
            $table->unsignedInteger('bonus_rendu')->default(0)->after('bonus_utilises');
        });

        Schema::table('abonnements', function (Blueprint $table) {
            $table->boolean('essai')->default(false)->after('statut');
            $table->unsignedInteger('montant_remise')->default(0)->after('prix_paye');
            $table->string('libelle_promo')->nullable()->after('montant_remise');
        });
    }

    public function down(): void
    {
        Schema::table('abonnements', fn (Blueprint $t) => $t->dropColumn(['essai', 'montant_remise', 'libelle_promo']));
        Schema::table('reservations', fn (Blueprint $t) => $t->dropColumn(['montant_bonus', 'bonus_utilises', 'bonus_rendu']));
        Schema::dropIfExists('bonus');
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('parrain_id');
            $t->dropUnique(['code_parrainage']);
            $t->dropColumn(['code_parrainage', 'parrainage_recompense_le']);
        });
    }
};
