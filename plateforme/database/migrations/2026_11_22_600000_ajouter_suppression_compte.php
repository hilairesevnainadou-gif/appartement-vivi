<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Date de suppression du compte par son titulaire : la ligne reste, sans donnée personnelle (pièces comptables). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->timestamp('supprime_le')->nullable()->index());
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['supprime_le']);
            $table->dropColumn('supprime_le');
        });
    }
};
