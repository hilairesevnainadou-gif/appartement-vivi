<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alertes de surveillance (App\Services\Surveillance) : panne, erreurs en série, tentatives d'intrusion,
 * webhook de paiement falsifié… Les incidents identiques (même type, même source) sont regroupés : compteur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertes_systeme', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->index();          // App\Services\Surveillance::TYPES
            $table->string('niveau', 15)->index();        // info, avertissement, critique
            $table->string('titre');
            $table->text('message');
            $table->string('source', 120)->nullable();    // adresse IP, identifiant, tâche…
            $table->json('details')->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('derniere_le')->nullable();
            $table->timestamp('notifiee_le')->nullable();
            $table->timestamp('resolue_le')->nullable()->index();
            $table->foreignId('resolue_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['type', 'source', 'resolue_le']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertes_systeme');
    }
};
