<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compte de retrait choisi par le propriétaire ou l'agent marketing (moyen, numéro, titulaire).
 * Il est recopié dans chaque demande de retrait : la caissière paie sur ce compte, même s'il change ensuite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('retrait_moyen', 40)->nullable();
            $table->string('retrait_numero', 40)->nullable();
            $table->string('retrait_titulaire', 120)->nullable();
            $table->timestamp('retrait_modifie_le')->nullable();
        });
        Schema::table('retraits', function (Blueprint $table) {
            $table->string('compte_moyen', 40)->nullable()->after('montant');
            $table->string('compte_numero', 40)->nullable()->after('compte_moyen');
            $table->string('compte_titulaire', 120)->nullable()->after('compte_numero');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['retrait_moyen', 'retrait_numero', 'retrait_titulaire', 'retrait_modifie_le']));
        Schema::table('retraits', fn (Blueprint $table) => $table->dropColumn(['compte_moyen', 'compte_numero', 'compte_titulaire']));
    }
};