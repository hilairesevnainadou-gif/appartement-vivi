<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Après-vente (lot 6) : rappel envoyé la veille, motif d'annulation choisi par le client. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dateTime('rappel_envoye_le')->nullable()->after('scanne_par');
            $table->dateTime('terminee_le')->nullable()->after('rappel_envoye_le');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['rappel_envoye_le', 'terminee_le']);
        });
    }
};
