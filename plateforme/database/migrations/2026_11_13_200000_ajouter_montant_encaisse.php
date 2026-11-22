<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réservation hors plateforme : le prix convenu (montant_total) et ce que le propriétaire a déjà encaissé
 * (acompte, ou tout) ; le reste est à payer par le client sur place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedInteger('montant_encaisse')->nullable()->after('montant_total');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', fn (Blueprint $t) => $t->dropColumn('montant_encaisse'));
    }
};
