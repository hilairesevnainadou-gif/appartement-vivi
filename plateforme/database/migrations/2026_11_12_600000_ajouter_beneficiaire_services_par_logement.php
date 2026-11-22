<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bénéficiaire des services à la carte (décision du 12/11/2026) : le prix revient à VIVI par défaut,
 * sauf pour les services que le propriétaire assure lui-même dans son logement (réglé par l'administrateur).
 */
return new class extends Migration
{
    public function up(): void
    {
        // null : valeur par défaut des paramètres ; 'proprietaire' ou 'vivi' : exception pour ce logement
        Schema::table('logement_service', function (Blueprint $table) {
            $table->string('beneficiaire', 20)->nullable();
        });

        // Bénéficiaire figé sur chaque service réservé, au moment du paiement
        Schema::table('reservation_services', function (Blueprint $table) {
            $table->string('beneficiaire', 20)->nullable()->after('montant');
        });

        // Part des services qui revient au propriétaire (créditée avec la location au démarrage du séjour)
        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedInteger('montant_services_proprietaire')->default(0)->after('montant_services');
        });
        DB::table('reservations')->where('services_beneficiaire', 'proprietaire')->update(['montant_services_proprietaire' => DB::raw('montant_services')]);
        DB::table('reservation_services')->whereIn('reservation_id', DB::table('reservations')->select('id')->where('services_beneficiaire', 'proprietaire'))
            ->update(['beneficiaire' => 'proprietaire']);
        DB::table('reservation_services')->whereNull('beneficiaire')->update(['beneficiaire' => 'vivi']);

        // Nouvelle valeur par défaut : VIVI
        DB::table('parametres')->where('cle', 'services_beneficiaire')->update(['valeur' => json_encode('vivi')]);
    }

    public function down(): void
    {
        Schema::table('reservations', fn (Blueprint $t) => $t->dropColumn('montant_services_proprietaire'));
        Schema::table('reservation_services', fn (Blueprint $t) => $t->dropColumn('beneficiaire'));
        Schema::table('logement_service', fn (Blueprint $t) => $t->dropColumn('beneficiaire'));
    }
};
