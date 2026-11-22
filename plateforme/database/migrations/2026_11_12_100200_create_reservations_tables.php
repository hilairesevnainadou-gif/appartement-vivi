<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réservations, services ajoutés, paiements FedaPay et avis.
 * Les montants et le taux de commission sont figés au moment de la réservation :
 * un changement de paramètre ne modifie jamais une réservation passée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();         // ex. VIVI-7K2M9P
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('logement_id')->constrained()->restrictOnDelete();
            $table->string('formule', 20);                     // App\Enums\Formule
            $table->foreignId('creneau_id')->nullable()->constrained('creneaux')->nullOnDelete();
            $table->dateTime('debut');
            $table->dateTime('fin');
            $table->unsignedTinyInteger('voyageurs')->default(1);
            $table->string('statut', 30)->default('en_attente_paiement')->index(); // App\Enums\StatutReservation
            $table->dateTime('bloque_jusqua')->nullable();     // créneau retenu pendant le paiement

            // Montants figés (FCFA)
            $table->unsignedInteger('montant_location');
            $table->unsignedInteger('montant_services')->default(0);
            $table->decimal('taux_commission', 5, 2);
            $table->unsignedInteger('montant_commission');
            $table->unsignedInteger('montant_total');
            $table->string('services_beneficiaire', 20)->default('proprietaire'); // paramètre au moment de la réservation

            // Accès
            $table->string('code_secours', 6);
            $table->dateTime('scanne_le')->nullable();
            $table->foreignId('scanne_par')->nullable()->constrained('users')->nullOnDelete();

            // Report et déplacement
            $table->dateTime('debut_initial')->nullable();
            $table->unsignedTinyInteger('nombre_reports')->default(0);
            $table->foreignId('deplacee_par')->nullable()->constrained('users')->nullOnDelete(); // propriétaire ou admin, hors délai

            // Annulation
            $table->dateTime('annulee_le')->nullable();
            $table->string('motif_annulation')->nullable();
            $table->unsignedInteger('montant_rembourse')->nullable();

            $table->timestamps();
            $table->index(['logement_id', 'debut', 'fin']);
        });

        Schema::create('reservation_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nom', 120);                         // copié : le catalogue peut changer
            $table->unsignedInteger('prix_unitaire');
            $table->unsignedSmallInteger('quantite')->default(1);
            $table->unsignedInteger('montant');
            $table->timestamps();
        });

        // Paiements reçus par FedaPay, pour une réservation ou un abonnement
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->morphs('payable');                          // Reservation ou Abonnement
            $table->foreignId('payeur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('passerelle', 20)->default('fedapay');
            $table->string('reference_passerelle')->nullable()->index(); // identifiant de transaction FedaPay
            $table->string('moyen', 40)->nullable();            // MTN Mobile Money, carte…
            $table->unsignedInteger('montant');
            $table->string('statut', 20)->default('en_attente')->index(); // App\Enums\StatutPaiement
            $table->json('donnees')->nullable();                // réponse brute de FedaPay
            $table->dateTime('paye_le')->nullable();
            $table->timestamps();
        });

        Schema::create('avis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('logement_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('note');                // 1 à 5
            $table->text('commentaire')->nullable();
            $table->string('statut', 20)->default('en_attente')->index(); // App\Enums\StatutAvis
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avis');
        Schema::dropIfExists('paiements');
        Schema::dropIfExists('reservation_services');
        Schema::dropIfExists('reservations');
    }
};
