<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications de l'application mobile :
 *  - appareils : jetons Firebase (FCM) des téléphones connectés, pour les notifications push ;
 *  - offres : messages envoyés par VIVI (promotions, nouveautés) depuis l'administration ;
 *  - users.recevoir_offres : la personne peut refuser les offres (les notifications de réservation restent).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appareils', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('jeton', 255)->unique();
            $table->string('plateforme', 20)->default('android');
            $table->string('nom', 120)->nullable();
            $table->timestamp('vu_le')->nullable();
            $table->timestamps();
        });

        Schema::create('offres', function (Blueprint $table) {
            $table->id();
            $table->string('titre', 120);
            $table->text('texte');
            $table->string('lien', 500)->nullable();
            $table->string('cible', 30)->default('clients');
            $table->foreignId('envoyee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('destinataires')->default(0);
            $table->timestamp('envoyee_le')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('recevoir_offres')->default(true)->after('actif');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('recevoir_offres'));
        Schema::dropIfExists('offres');
        Schema::dropIfExists('appareils');
    }
};
