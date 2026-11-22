<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VIVI accueille aussi des clients étrangers : à la réservation, le client donne un numéro WhatsApp de n'importe
 * quel pays (format international, ex. +33612345678), pour que le gérant puisse le joindre.
 * users.whatsapp garde le dernier numéro donné, pour préremplir la réservation suivante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('whatsapp', 20)->nullable()->after('client_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('whatsapp', 20)->nullable()->after('telephone');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', fn (Blueprint $table) => $table->dropColumn('whatsapp'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('whatsapp'));
    }
};
