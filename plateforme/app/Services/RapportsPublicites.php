<?php

namespace App\Services;

use App\Models\Publicite;
use App\Notifications\RapportPublicite;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Notification;

/**
 * Rapports de publicité envoyés automatiquement à l'annonceur (tâche « vivi:rapports-publicites », chaque matin) :
 *  - « Chaque semaine » : le lundi, la semaine écoulée ;
 *  - « Chaque mois » : le 1er, le mois écoulé ;
 *  - dans tous les cas (sauf « Jamais ») : un bilan final le lendemain de la date de fin.
 * Destinataires : le compte VIVI de l'annonceur (email + espace Notifications) et l'adresse saisie, sans doublon.
 */
class RapportsPublicites
{
    /** Bilan final : seulement pour les diffusions finies depuis moins de 7 jours (pas de rattrapage ancien). */
    private const RATTRAPAGE_JOURS = 7;

    public function envoyerLesRapportsDus(?CarbonInterface $aujourdhui = null): int
    {
        $jour = CarbonImmutable::parse($aujourdhui ?? today())->startOfDay();
        $envoyes = 0;

        Publicite::with('annonceurCompte')
            ->where('rapport_frequence', '!=', 'aucun')
            ->where(fn ($q) => $q->whereNotNull('rapport_email')->orWhereNotNull('annonceur_id'))
            ->where(fn ($q) => $q->whereNull('rapport_envoye_le')->orWhere('rapport_envoye_le', '<', $jour))
            ->each(function (Publicite $p) use ($jour, &$envoyes) {
                if ($periode = $this->periodeDue($p, $jour)) {
                    $envoyes += (int) $this->envoyer($p, ...$periode);
                }
            });

        return $envoyes;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: bool}|null  du, au, bilan final */
    private function periodeDue(Publicite $p, CarbonImmutable $jour): ?array
    {
        $hier = $jour->subDay();
        $debut = $p->debut ? CarbonImmutable::parse($p->debut) : CarbonImmutable::parse($p->created_at)->startOfDay();

        // Diffusion terminée : bilan final une fois, sur toute la durée
        if ($p->fin !== null && $p->fin->lt($jour)) {
            $fin = CarbonImmutable::parse($p->fin);
            $dejaFait = $p->rapport_envoye_le !== null && $p->rapport_envoye_le->gt($fin->endOfDay());

            return ! $dejaFait && $fin->gte($jour->subDays(self::RATTRAPAGE_JOURS)) ? [$debut, $fin, true] : null;
        }
        if ($debut->gt($hier) || ! $p->actif) {
            return null;   // pas encore commencée, ou en pause
        }

        return match ($p->rapport_frequence) {
            'hebdomadaire' => $jour->isMonday() ? [$jour->subWeek()->max($debut), $hier, false] : null,
            'mensuel' => $jour->day === 1 ? [$jour->subMonth()->max($debut), $hier, false] : null,
            default => null,
        };
    }

    /** Envoie le rapport tout de suite (aussi le bouton « Envoyer le rapport » de l'administration). Faux sans destinataire. */
    public function envoyer(Publicite $p, CarbonInterface $du, CarbonInterface $au, bool $final = false): bool
    {
        $notification = new RapportPublicite($p, CarbonImmutable::parse($du), CarbonImmutable::parse($au), $final);
        $compte = $p->annonceurCompte;
        $envoye = false;

        if ($compte) {
            $compte->notify($notification);
            $envoye = true;
        }
        $email = $p->rapport_email ? mb_strtolower(trim($p->rapport_email)) : null;
        if ($email && $email !== mb_strtolower((string) $compte?->email)) {
            Notification::route('mail', $email)->notify($notification);
            $envoye = true;
        }
        if ($envoye) {
            $p->forceFill(['rapport_envoye_le' => now()])->saveQuietly();
        }

        return $envoye;
    }
}
