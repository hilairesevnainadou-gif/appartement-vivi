<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - logements.mode_gestion :
 *   - « proprietaire » : il accueille lui-même ou avec ses gestionnaires ;
 *   - « vivi » : il confie l'accueil à VIVI ; l'administration désigne un gérant VIVI (gerant_vivi_id).
 * - formules_abonnement.salles_max : quota de salles (fêtes, conférences…) séparé de celui des logements.
 *   Chaque formule inclut au moins 2 logements et 1 salle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logements', function (Blueprint $table) {
            $table->string('mode_gestion', 20)->default('proprietaire')->after('en_ligne');
            $table->foreignId('gerant_vivi_id')->nullable()->after('mode_gestion')->constrained('users')->nullOnDelete();
            $table->timestamp('gestion_vivi_demandee_le')->nullable()->after('gerant_vivi_id');
        });

        Schema::table('formules_abonnement', function (Blueprint $table) {
            $table->unsignedSmallInteger('salles_max')->default(1)->after('logements_max');
        });

        // Au moins 2 logements et 1 salle dans chaque formule ; les grandes formules gardent leur quota
        DB::table('formules_abonnement')->where('logements_max', '<', 2)->update(['logements_max' => 2]);
        foreach (['Essentiel' => 1, 'Confort' => 2, 'Premium' => 5] as $nom => $salles) {
            DB::table('formules_abonnement')->where('nom', $nom)->update(['salles_max' => $salles]);
        }
        // Les quotas sont désormais affichés à part (« 2 logements et 1 salle ») : on retire les avantages qui les répétaient
        foreach (DB::table('formules_abonnement')->get(['id', 'avantages']) as $f) {
            $avantages = array_values(array_filter((array) json_decode((string) $f->avantages, true),
                fn ($a) => ! preg_match('/logements? publiés?/iu', (string) $a)));
            DB::table('formules_abonnement')->where('id', $f->id)->update(['avantages' => json_encode($avantages, JSON_UNESCAPED_UNICODE)]);
        }
    }

    public function down(): void
    {
        Schema::table('formules_abonnement', fn (Blueprint $table) => $table->dropColumn('salles_max'));
        Schema::table('logements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gerant_vivi_id');
            $table->dropColumn(['mode_gestion', 'gestion_vivi_demandee_le']);
        });
    }
};
