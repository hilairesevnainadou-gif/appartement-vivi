<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Prix d'une publicité et règlements de l'annonceur (en une ou plusieurs fois). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publicites', function (Blueprint $table) {
            $table->string('annonceur_contact', 120)->nullable()->after('annonceur');
            $table->unsignedInteger('prix')->default(0)->after('ordre');
        });

        Schema::create('publicite_paiements', function (Blueprint $table) {
            $table->id();
            // Une publicité déjà encaissée ne se supprime pas
            $table->foreignId('publicite_id')->constrained('publicites')->restrictOnDelete();
            $table->unsignedInteger('montant');
            $table->string('mode', 60);
            $table->string('reference', 80)->nullable();
            $table->date('paye_le')->index();
            $table->foreignId('encaisse_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publicite_paiements');
        Schema::table('publicites', fn (Blueprint $table) => $table->dropColumn(['annonceur_contact', 'prix']));
    }
};
