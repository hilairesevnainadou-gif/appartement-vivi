<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Publicités : plusieurs espaces par publicité, dans l'application et/ou sur le site web ; annonceur relié à un
 * compte VIVI ; rapports de vues et de clics envoyés automatiquement à l'annonceur. Vues et clics du site comptés à part.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publicites', function (Blueprint $table) {
            $table->json('espaces_application')->nullable()->after('emplacement');
            $table->json('espaces_site')->nullable()->after('espaces_application');
            $table->foreignId('annonceur_id')->nullable()->after('annonceur')->constrained('users')->nullOnDelete();
            $table->string('rapport_email', 190)->nullable()->after('annonceur_contact');
            $table->string('rapport_frequence', 20)->default('aucun')->after('rapport_email');
            $table->timestamp('rapport_envoye_le')->nullable()->after('rapport_frequence');
        });

        // Les publicités existantes gardent leur espace dans l'application, et s'affichent au même endroit sur le site
        $site = ['accueil_haut', 'accueil_milieu', 'recherche', 'fiche', 'reservations'];
        foreach (DB::table('publicites')->get(['id', 'emplacement']) as $p) {
            DB::table('publicites')->where('id', $p->id)->update([
                'espaces_application' => json_encode([$p->emplacement]),
                'espaces_site' => json_encode(in_array($p->emplacement, $site, true) ? [$p->emplacement] : []),
            ]);
        }

        Schema::table('publicite_jours', function (Blueprint $table) {
            $table->unsignedInteger('vues_site')->default(0)->after('clics');
            $table->unsignedInteger('clics_site')->default(0)->after('vues_site');
        });
    }

    public function down(): void
    {
        Schema::table('publicite_jours', fn (Blueprint $table) => $table->dropColumn(['vues_site', 'clics_site']));
        Schema::table('publicites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('annonceur_id');
            $table->dropColumn(['espaces_application', 'espaces_site', 'rapport_email', 'rapport_frequence', 'rapport_envoye_le']);
        });
    }
};
