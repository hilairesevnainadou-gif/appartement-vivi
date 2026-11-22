<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Caisse (lot 7) : versement des remboursements dus après une annulation. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dateTime('rembourse_le')->nullable()->after('montant_rembourse');
            $table->foreignId('rembourse_par')->nullable()->after('rembourse_le')->constrained('users')->nullOnDelete();
            $table->string('mode_remboursement', 40)->nullable()->after('rembourse_par');
            $table->string('reference_remboursement')->nullable()->after('mode_remboursement');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rembourse_par');
            $table->dropColumn(['rembourse_le', 'mode_remboursement', 'reference_remboursement']);
        });
    }
};
