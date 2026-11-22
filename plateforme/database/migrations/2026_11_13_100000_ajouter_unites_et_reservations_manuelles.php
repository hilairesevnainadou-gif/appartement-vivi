<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 13/11/2026 :
 *  - résidences à plusieurs chambres identiques louées séparément : un logement a N unités, réservable
 *    tant qu'une unité est libre (même tarif, mêmes créneaux) ;
 *  - réservation saisie par le propriétaire ou le gestionnaire pour un client venu hors de la plateforme
 *    (téléphone, sur place) : elle occupe le calendrier, sans paiement ni commission VIVI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logements', function (Blueprint $table) {
            $table->unsignedSmallInteger('unites')->default(1)->after('salles_de_bain');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->string('origine', 20)->default('plateforme')->after('statut');   // plateforme, manuelle
            $table->foreignId('cree_par')->nullable()->after('origine')->constrained('users')->nullOnDelete();
            $table->string('note_interne', 255)->nullable()->after('cree_par');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cree_par');
            $table->dropColumn(['origine', 'note_interne']);
        });
        Schema::table('logements', fn (Blueprint $t) => $t->dropColumn('unites'));
    }
};
