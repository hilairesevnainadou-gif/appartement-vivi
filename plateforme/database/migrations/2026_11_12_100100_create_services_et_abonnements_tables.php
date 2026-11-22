<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue des services à la carte (prix fixés par VIVI) et abonnements des propriétaires.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 120);
            $table->text('description')->nullable();
            $table->unsignedInteger('prix');
            $table->string('unite', 40)->default('la prestation'); // « le pack », « par personne »…
            $table->string('icone', 60)->nullable();
            $table->boolean('actif')->default(true);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });

        // Services que le propriétaire assure dans ce logement
        Schema::create('logement_service', function (Blueprint $table) {
            $table->foreignId('logement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->primary(['logement_id', 'service_id']);
        });

        // Formules paramétrées par l'administrateur
        Schema::create('formules_abonnement', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 60);
            $table->unsignedInteger('prix_mensuel');
            $table->unsignedSmallInteger('logements_max');
            $table->unsignedSmallInteger('gestionnaires_max')->default(0);
            $table->json('avantages')->nullable();             // liste de phrases affichées
            $table->boolean('actif')->default(true);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });

        Schema::create('abonnements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proprietaire_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('formule_abonnement_id')->constrained('formules_abonnement')->restrictOnDelete();
            $table->date('debut');
            $table->date('fin');
            $table->unsignedInteger('prix_paye');              // prix au moment de la souscription
            $table->string('statut', 20)->default('actif')->index(); // App\Enums\StatutAbonnement
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abonnements');
        Schema::dropIfExists('formules_abonnement');
        Schema::dropIfExists('logement_service');
        Schema::dropIfExists('services');
    }
};
