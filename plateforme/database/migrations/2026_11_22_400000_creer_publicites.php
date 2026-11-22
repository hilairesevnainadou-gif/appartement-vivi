<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Publicités de l'application Android, avec les vues et les clics par jour. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publicites', function (Blueprint $table) {
            $table->id();
            $table->string('titre', 80);
            $table->string('annonceur', 80)->nullable();
            $table->string('texte', 160)->nullable();
            $table->string('bouton', 30)->nullable();
            $table->string('lien', 500)->nullable();
            $table->string('image');
            $table->string('emplacement', 30)->index();
            $table->json('villes')->nullable();
            $table->date('debut')->nullable();
            $table->date('fin')->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('publicite_jours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publicite_id')->constrained('publicites')->cascadeOnDelete();
            $table->date('jour');
            $table->unsignedInteger('vues')->default(0);
            $table->unsignedInteger('clics')->default(0);
            $table->unique(['publicite_id', 'jour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publicite_jours');
        Schema::dropIfExists('publicites');
    }
};
