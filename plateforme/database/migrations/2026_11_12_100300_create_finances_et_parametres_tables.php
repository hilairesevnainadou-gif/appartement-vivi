<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solde des propriétaires, demandes de retrait et paramètres de la plateforme.
 *
 * Le solde n'est pas stocké : c'est la somme des mouvements. Chaque séjour démarré
 * (QR Code scanné) crédite le solde ; chaque retrait validé le débite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retraits', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();         // ex. RET-000013
            $table->foreignId('proprietaire_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('montant');
            $table->string('statut', 20)->default('en_attente')->index(); // App\Enums\StatutRetrait
            $table->string('mode_reglement', 40)->nullable();   // choisi par la caissière à la validation
            $table->string('reference_reglement')->nullable();  // n° de transaction Mobile Money, de virement…
            $table->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('traite_le')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('mouvements_solde', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proprietaire_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 20);                         // App\Enums\TypeMouvement
            $table->integer('montant');                         // positif = crédit, négatif = débit
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('retrait_id')->nullable()->constrained()->nullOnDelete();
            $table->string('libelle');
            $table->timestamps();
            $table->index(['proprietaire_id', 'created_at']);
        });

        // Réglages modifiables par l'administrateur (commission, notifications…)
        Schema::create('parametres', function (Blueprint $table) {
            $table->id();
            $table->string('cle', 80)->unique();
            $table->json('valeur')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametres');
        Schema::dropIfExists('mouvements_solde');
        Schema::dropIfExists('retraits');
    }
};
