<?php

namespace App\Support;

use App\Enums\EmplacementPub;
use App\Enums\SourcePub;
use App\Http\Resources\Api\Format;
use App\Models\Publicite;

/**
 * Espaces publicitaires de l'application Android et du site web : publicités de VIVI (Administration › Publicités) et
 * annonces Google AdMob (« Monetize » dans Firebase). Réglages : Administration › Paramètres.
 */
class Publicites
{
    /** Publicités de VIVI au plus par espace (l'application les fait tourner). */
    private const MAX_PAR_ESPACE = 5;

    public static function actives(): bool
    {
        return (bool) Parametres::get('pub_active');
    }

    /**
     * Identifiant du bloc AdMob à afficher dans cet espace, ou null.
     * En mode test : le bloc de démonstration de Google, qui ne rapporte rien mais ne risque pas de bloquer le compte.
     */
    public static function blocAdmob(EmplacementPub $e): ?string
    {
        if (! Parametres::get('admob_actif')) {
            return null;
        }
        if (Parametres::get('admob_test')) {
            return $e->blocTest();
        }

        return Parametres::get('pub_emplacements')[$e->value]['admob'] ?: null;
    }

    /** Réponse de GET /api/v1/publicites. */
    public static function pourApplication(?string $ville = null): array
    {
        $active = self::actives();
        $reglages = Parametres::get('pub_emplacements');
        $publicites = $active
            ? Publicite::diffusees()->orderBy('ordre')->orderByDesc('id')->get()->filter(fn (Publicite $p) => $p->concerneVille($ville))
            : collect();

        return [
            'active' => $active,
            'mention' => 'Publicité',
            'frequence_liste' => (int) Parametres::get('pub_frequence_liste'),
            'plein_ecran_heures' => (int) Parametres::get('pub_plein_ecran_heures'),
            'admob_test' => (bool) Parametres::get('admob_test'),
            'emplacements' => collect(EmplacementPub::cases())->mapWithKeys(function (EmplacementPub $e) use ($active, $reglages, $publicites) {
                $source = SourcePub::from($reglages[$e->value]['source']);
                $annonces = $active && $source->vivi()
                    ? $publicites->filter(fn (Publicite $p) => $p->estDans($e))->take(self::MAX_PAR_ESPACE)->map(fn (Publicite $p) => self::annonce($p))->values()->all()
                    : [];
                $bloc = $active && $source->admob() ? self::blocAdmob($e) : null;
                [$largeur, $hauteur] = $e->taille();

                return [$e->value => [
                    'libelle' => $e->getLabel(),
                    'format' => $e->format(),
                    'largeur' => $largeur,
                    'hauteur' => $hauteur,
                    'annonces' => $annonces,
                    'admob' => $bloc ? ['format' => $e->formatAdmob(), 'bloc' => $bloc] : null,
                ]];
            })->all(),
        ];
    }

    /**
     * Publicités de VIVI à montrer sur le site web, dans cet espace (visiteurs connectés ou non).
     * Les annonces AdMob ne concernent que l'application : sur le site, seules les publicités de VIVI s'affichent.
     *
     * @return \Illuminate\Support\Collection<int, Publicite>
     */
    public static function pourSite(EmplacementPub $e, ?string $ville = null): \Illuminate\Support\Collection
    {
        if (! self::actives() || ! SourcePub::from(Parametres::get('pub_emplacements')[$e->value]['source'])->vivi()) {
            return collect();
        }
        // Une seule requête par page, même avec plusieurs espaces
        $diffusees = once(fn () => Publicite::diffusees()->orderBy('ordre')->orderByDesc('id')->get());

        return $diffusees
            ->filter(fn (Publicite $p) => $p->estDans($e, Publicite::SITE) && $p->concerneVille($ville))
            ->take(self::MAX_PAR_ESPACE)->values();
    }

    private static function annonce(Publicite $p): array
    {
        return [
            'id' => $p->id,
            'titre' => $p->titre,
            'annonceur' => $p->annonceur,
            'texte' => $p->texte,
            'bouton' => $p->lien ? ($p->bouton ?: 'En savoir plus') : null,
            'image' => Format::photo($p->image),
            'lien' => $p->lien,
            // Vrai : page de VIVI, à ouvrir dans l'application ; faux : navigateur
            'lien_interne' => $p->lien !== null && (str_starts_with($p->lien, url('/').'/') || $p->lien === url('/')),
        ];
    }

    /** Contenu de /app-ads.txt, demandé par AdMob sur le site du développeur ; null sans identifiant éditeur. */
    public static function appAdsTxt(): ?string
    {
        $editeur = (string) Parametres::get('admob_editeur');

        return preg_match('/^pub-\d{16}$/', $editeur) ? "google.com, {$editeur}, DIRECT, f08c47fec0942fa0\n" : null;
    }
}
