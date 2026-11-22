<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agents marketing : des personnes (souvent des clients) qui font la promotion de logements par un lien
 * d'affiliation. Le propriétaire fixe, par logement, la part qui revient à l'agent (pourcentage de la
 * location ou montant fixe) ; elle est prise sur la part du propriétaire. Sans agent, elle revient à VIVI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logements', function (Blueprint $table) {
            $table->string('marketing_type', 12)->nullable()->after('temps_preparation_minutes');   // pourcentage | fixe | null
            $table->decimal('marketing_valeur', 10, 2)->default(0)->after('marketing_type');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('code_agent', 12)->nullable()->unique()->after('code_parrainage');
        });

        Schema::create('logement_agent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['logement_id', 'agent_id']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->after('client_id')->constrained('users')->nullOnDelete();
            $table->string('marketing_type', 12)->nullable()->after('montant_commission');
            $table->decimal('marketing_valeur', 10, 2)->default(0)->after('marketing_type');
            $table->unsignedInteger('montant_marketing')->default(0)->after('marketing_valeur');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_id');
            $table->dropColumn(['marketing_type', 'marketing_valeur', 'montant_marketing']);
        });
        Schema::dropIfExists('logement_agent');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('code_agent'));
        Schema::table('logements', fn (Blueprint $table) => $table->dropColumn(['marketing_type', 'marketing_valeur']));
    }
};
