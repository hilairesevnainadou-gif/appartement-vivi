<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\NotificationEquipe;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Prévient l'équipe VIVI dans la cloche de l'administration : les administrateurs, et les membres
 * dont le rôle a la permission concernée (ex. « retraits.gerer » pour la caissière).
 */
final class Equipe
{
    /** @return Collection<int, User> */
    public static function concernes(string $permission): Collection
    {
        $admins = User::where('actif', true)->role(Role::Administrateur->value)->get();
        try {
            $autres = User::where('actif', true)->permission($permission)->get();
        } catch (Throwable) {
            $autres = collect();   // permission pas encore créée (base non initialisée)
        }

        return $admins->merge($autres)->unique('id')->values();
    }

    public static function prevenir(string $permission, string $titre, string $texte, ?string $url = null, string $icone = 'heroicon-o-bell', string $couleur = 'primary', string $action = 'Ouvrir'): void
    {
        try {
            $destinataires = self::concernes($permission);
            if ($destinataires->isNotEmpty()) {
                Notification::send($destinataires, new NotificationEquipe($titre, $texte, $url, $icone, $couleur, $action));
            }
        } catch (Throwable $e) {
            report($e);   // une notification qui échoue ne doit jamais bloquer l'opération
        }
    }
}
