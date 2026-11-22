<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logements et tout ce qui décrit leur disponibilité :
 * équipements, photos, tarifs par formule, créneaux de demi-journée,
 * jours bloqués et délégation à un gestionnaire.
 * Les montants sont en FCFA entiers (pas de centimes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proprietaire_id')->constrained('users')->cascadeOnDelete();
            $table->string('titre');
            $table->string('slug')->unique();
            $table->string('type', 20);                       // App\Enums\TypeLogement
            $table->string('ville', 80)->index();
            $table->string('quartier', 120);
            $table->string('adresse')->nullable();            // communiquée au client après paiement
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('capacite')->default(2);
            $table->unsignedTinyInteger('chambres')->default(1);
            $table->unsignedTinyInteger('lits')->default(1);
            $table->unsignedTinyInteger('salles_de_bain')->default(1);
            $table->unsignedSmallInteger('temps_preparation_minutes')->default(60); // bloqué après chaque départ
            $table->string('statut', 20)->default('brouillon')->index();          // App\Enums\StatutLogement
            $table->boolean('en_ligne')->default(true);       // pause décidée par le propriétaire
            $table->text('motif_refus')->nullable();
            $table->timestamp('valide_le')->nullable();
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('equipements', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 80)->unique();
            $table->string('icone', 60)->nullable();          // nom Bootstrap Icons, ex. bi-wifi
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });

        Schema::create('equipement_logement', function (Blueprint $table) {
            $table->foreignId('logement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipement_id')->constrained()->cascadeOnDelete();
            $table->primary(['logement_id', 'equipement_id']);
        });

        Schema::create('logement_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logement_id')->constrained()->cascadeOnDelete();
            $table->string('chemin');
            $table->string('legende')->nullable();
            $table->unsignedSmallInteger('ordre')->default(0); // 0 = photo principale
            $table->timestamps();
        });

        // Un tarif par formule proposée ; une formule absente ou inactive n'est pas réservable.
        Schema::create('tarifs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logement_id')->constrained()->cascadeOnDelete();
            $table->string('formule', 20);                    // App\Enums\Formule
            $table->unsignedInteger('prix');                  // par heure, par créneau ou par jour
            $table->boolean('actif')->default(true);
            // Formule « heure »
            $table->time('heure_ouverture')->nullable();
            $table->time('heure_fermeture')->nullable();
            $table->unsignedTinyInteger('duree_minimum_heures')->nullable();
            // Formule « journée »
            $table->time('heure_arrivee')->nullable();
            $table->time('heure_depart')->nullable();         // le lendemain
            $table->timestamps();
            $table->unique(['logement_id', 'formule']);
        });

        // Créneaux de demi-journée nommés librement par le propriétaire (ex. Matin 08:00–13:00)
        Schema::create('creneaux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logement_id')->constrained()->cascadeOnDelete();
            $table->string('nom', 60);
            $table->time('heure_debut');
            $table->time('heure_fin');                        // peut être après minuit (fin < début)
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });

        Schema::create('blocages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logement_id')->constrained()->cascadeOnDelete();
            $table->date('date_debut');
            $table->date('date_fin');
            $table->string('motif')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['logement_id', 'date_debut', 'date_fin']);
        });

        // Logements confiés par un propriétaire à un gestionnaire
        Schema::create('delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gestionnaire_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['logement_id', 'gestionnaire_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delegations');
        Schema::dropIfExists('blocages');
        Schema::dropIfExists('creneaux');
        Schema::dropIfExists('tarifs');
        Schema::dropIfExists('logement_photos');
        Schema::dropIfExists('equipement_logement');
        Schema::dropIfExists('equipements');
        Schema::dropIfExists('logements');
    }
};
