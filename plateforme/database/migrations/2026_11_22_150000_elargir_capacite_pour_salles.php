<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Salles de fêtes et de conférence : jusqu'à 1 000 personnes. La capacité d'un logement et le nombre de
 * voyageurs d'une réservation dépassaient la limite des petits entiers MySQL (255).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logements', fn (Blueprint $table) => $table->unsignedSmallInteger('capacite')->default(2)->change());
        Schema::table('reservations', fn (Blueprint $table) => $table->unsignedSmallInteger('voyageurs')->default(1)->change());
    }

    public function down(): void
    {
        Schema::table('logements', fn (Blueprint $table) => $table->unsignedTinyInteger('capacite')->default(2)->change());
        Schema::table('reservations', fn (Blueprint $table) => $table->unsignedTinyInteger('voyageurs')->default(1)->change());
    }
};
