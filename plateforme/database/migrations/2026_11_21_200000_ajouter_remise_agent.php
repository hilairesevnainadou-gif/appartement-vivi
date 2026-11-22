<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un agent marketing qui réserve un logement qu'il promeut ne paie pas sa propre part marketing : elle est
 * déduite de son total (remise_agent). montant_total est déjà le total après remise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedInteger('remise_agent')->default(0)->after('montant_marketing');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', fn (Blueprint $table) => $table->dropColumn('remise_agent'));
    }
};
