<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Offre;
use App\Models\User;
use App\Notifications\OffreVivi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

/**
 * Offres et annonces de VIVI : déposées dans l'espace Notifications (site et application) et envoyées en push.
 * Seulement aux comptes actifs qui acceptent les offres (users.recevoir_offres, réglable dans le profil).
 */
class Offres
{
    /** Destinataires d'une cible (voir Offre::CIBLES). */
    public function destinataires(string $cible): Builder
    {
        $roles = match ($cible) {
            'agents' => [Role::AgentMarketing->value],
            'proprietaires' => [Role::Proprietaire->value],
            'tous' => [Role::Client->value, Role::Proprietaire->value, Role::Gestionnaire->value, Role::AgentMarketing->value],
            default => [Role::Client->value],
        };

        return User::query()->where('actif', true)->where('recevoir_offres', true)->role($roles);
    }

    /** @return int nombre de personnes prévenues */
    public function envoyer(Offre $offre, ?User $auteur = null): int
    {
        $total = 0;
        $this->destinataires($offre->cible)->select('users.*')->chunkById(200, function ($lot) use ($offre, &$total) {
            Notification::send($lot, new OffreVivi($offre));
            $total += $lot->count();
        }, 'users.id', 'id');

        $offre->update(['destinataires' => $total, 'envoyee_le' => now(), 'envoyee_par' => $auteur?->getKey() ?? $offre->envoyee_par]);

        return $total;
    }
}
