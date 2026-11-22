<?php

namespace App\Services;

use App\Models\Bonus;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\BonusRecu;
use App\Support\Parametres;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Parrainage et bonus.
 *
 *  - Chaque utilisateur a un code d'invitation. Un ami qui crée son compte avec ce code devient son filleul
 *    (et reçoit un bonus de bienvenue si l'administrateur en a fixé un).
 *  - Au premier séjour démarré du filleul (QR Code scanné), le parrain reçoit son bonus, une seule fois par filleul.
 *    On attend le séjour, et pas le paiement, pour qu'une réservation payée puis annulée ne rapporte rien.
 *  - Un bonus se déduit du total d'une réservation, dans la limite d'un pourcentage du total : le client paie
 *    toujours une partie par FedaPay. Le propriétaire reçoit sa location en entier ; c'est VIVI qui finance le bonus.
 *  - Bonus réservé au paiement, rendu si le paiement n'aboutit pas, et rendu en priorité en cas d'annulation remboursée.
 */
class Parrainage
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';   // sans 0/O ni 1/I, faciles à confondre

    public function actif(): bool
    {
        return (bool) Parametres::get('parrainage_actif');
    }

    /** Code d'invitation de l'utilisateur, créé au premier besoin : « GERA-7K3P ». */
    public function codeDe(User $user): string
    {
        if ($user->code_parrainage) {
            return $user->code_parrainage;
        }
        $prefixe = Str::upper(Str::substr(preg_replace('/[^A-Za-z]/', '', Str::ascii($user->prenom())), 0, 4)) ?: 'VIVI';
        do {
            $code = $prefixe.'-'.collect(range(1, 4))->map(fn () => self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)])->implode('');
        } while (User::where('code_parrainage', $code)->exists());
        $user->forceFill(['code_parrainage' => $code])->save();

        return $code;
    }

    /** Parrain correspondant à un code saisi (sans tenir compte des majuscules ni des espaces), ou null. */
    public function parrainDuCode(?string $code): ?User
    {
        $code = Str::upper(preg_replace('/\s+/', '', (string) $code));

        return $code === '' ? null : User::where('code_parrainage', $code)->where('actif', true)->first();
    }

    /** À la création d'un compte avec un code d'invitation : lien avec le parrain et bonus de bienvenue. */
    public function rattacher(User $filleul, ?string $code): void
    {
        if (! $this->actif() || $filleul->parrain_id) {
            return;
        }
        $parrain = $this->parrainDuCode($code);
        if (! $parrain || $parrain->is($filleul)) {
            return;
        }
        $filleul->forceFill(['parrain_id' => $parrain->getKey()])->save();

        $montant = (int) Parametres::get('parrainage_bonus_filleul');
        if ($montant > 0) {
            $this->crediter($filleul, $montant, Bonus::MOTIF_BIENVENUE, 'Bienvenue : invité par '.$parrain->prenom());
        }
    }

    /** Premier séjour démarré d'un filleul : le parrain reçoit son bonus (une seule fois). */
    public function recompenser(Reservation $reservation): ?Bonus
    {
        if (! $this->actif()) {
            return null;
        }
        $bonus = DB::transaction(function () use ($reservation) {
            $filleul = User::whereKey($reservation->client_id)->lockForUpdate()->first();
            if (! $filleul?->parrain_id || $filleul->parrainage_recompense_le) {
                return null;
            }
            $parrain = User::find($filleul->parrain_id);
            $montant = (int) Parametres::get('parrainage_bonus_parrain');
            $filleul->forceFill(['parrainage_recompense_le' => now()])->save();
            if (! $parrain?->actif || $montant <= 0) {
                return null;
            }

            return $this->crediter($parrain, $montant, Bonus::MOTIF_PARRAINAGE, $filleul->prenom().' a fait son premier séjour', $filleul);
        });

        if ($bonus) {
            try {
                $bonus->user->notify(new BonusRecu($bonus));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $bonus;
    }

    public function crediter(User $user, int $montant, string $motif, string $libelle, ?User $filleul = null, ?User $auteur = null): Bonus
    {
        return $user->bonus()->create([
            'motif' => $motif, 'libelle' => $libelle, 'montant' => $montant, 'restant' => $montant,
            'expire_le' => today()->addDays(max(1, (int) Parametres::get('parrainage_validite_jours'))),
            'filleul_id' => $filleul?->getKey(), 'cree_par' => $auteur?->getKey(),
        ]);
    }

    /** Bonus utilisable aujourd'hui, en FCFA. */
    public function disponible(User $user): int
    {
        return (int) $user->bonus()->utilisables()->sum('restant');
    }

    /** Part d'un total payable avec le bonus : le bonus disponible, dans la limite du plafond. */
    public function applicable(User $user, int $total, ?Reservation $dejaRetenuPour = null): int
    {
        $plafond = (int) floor($total * max(0, min(90, (int) Parametres::get('bonus_plafond_pourcentage'))) / 100);
        // Le bonus déjà retenu pour cette réservation (paiement en cours) reste à elle
        $disponible = $this->disponible($user) + (int) $dejaRetenuPour?->montant_bonus;

        return max(0, min($disponible, $plafond));
    }

    /**
     * Retient le bonus pour une réservation (à appeler sous transaction), les bonus qui expirent le plus tôt d'abord.
     * Le détail est gardé sur la réservation pour pouvoir le rendre.
     */
    public function reserver(Reservation $reservation, int $montant): int
    {
        $reste = $montant;
        $utilises = [];
        $lignes = Bonus::where('user_id', $reservation->client_id)->utilisables()->orderBy('expire_le')->orderBy('id')->lockForUpdate()->get();
        foreach ($lignes as $ligne) {
            if ($reste <= 0) {
                break;
            }
            $pris = min($reste, $ligne->restant);
            $ligne->decrement('restant', $pris);
            $utilises[] = ['bonus_id' => $ligne->getKey(), 'montant' => $pris];
            $reste -= $pris;
        }
        $reservation->forceFill(['montant_bonus' => $montant - $reste, 'bonus_utilises' => $utilises ?: null])->save();

        return $montant - $reste;
    }

    /** Paiement abandonné ou remplacé : tout le bonus retenu revient au client. */
    public function liberer(Reservation $reservation): void
    {
        $this->rendre($reservation, (int) $reservation->montant_bonus);
        $reservation->forceFill(['montant_bonus' => 0, 'bonus_utilises' => null, 'bonus_rendu' => 0])->save();
    }

    /** Rend au client jusqu'à $montant du bonus utilisé sur cette réservation (annulation remboursée). */
    public function rendre(Reservation $reservation, int $montant): int
    {
        $rendu = 0;
        foreach ((array) $reservation->bonus_utilises as $u) {
            if ($rendu >= $montant) {
                break;
            }
            $part = min($montant - $rendu, (int) $u['montant']);
            Bonus::whereKey($u['bonus_id'])->increment('restant', $part);
            $rendu += $part;
        }

        return $rendu;
    }
}
