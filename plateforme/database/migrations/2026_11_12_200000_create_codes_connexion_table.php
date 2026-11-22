<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Codes à 6 chiffres envoyés par email ou SMS pour se connecter.
 * Le code n'est jamais stocké en clair : seulement son empreinte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codes_connexion', function (Blueprint $table) {
            $table->id();
            $table->string('identifiant')->index();     // email ou téléphone normalisé
            $table->string('canal', 10);                // mail ou sms
            $table->string('empreinte', 64);            // sha256 du code
            $table->dateTime('expire_le');
            $table->unsignedTinyInteger('tentatives')->default(0);
            $table->dateTime('utilise_le')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codes_connexion');
    }
};
