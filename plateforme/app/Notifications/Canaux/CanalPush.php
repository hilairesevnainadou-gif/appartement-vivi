<?php

namespace App\Notifications\Canaux;

use App\Services\NotificationsPush;
use Illuminate\Notifications\Notification;

/**
 * Canal « push » : notification affichée sur le téléphone (application Android), avec le même titre et le
 * même texte que dans l'espace Notifications. Les données indiquent à l'application quel écran ouvrir.
 */
class CanalPush
{
    public function __construct(private readonly NotificationsPush $push) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toDatabase')) {
            return;
        }
        $n = $notification->toDatabase($notifiable);
        $cible = $n['cible'] ?? [];

        $this->push->envoyer($notifiable, $n['titre'] ?? 'VIVI', $n['texte'] ?? '', [
            'notification_id' => $notification->id,
            'type' => class_basename($notification),
            'categorie' => $n['categorie'] ?? 'compte',
            'ecran' => $cible['ecran'] ?? 'notifications',
            'reference' => $cible['reference'] ?? null,
            'id' => $cible['id'] ?? null,
            'lien' => $n['lien'] ?? null,
        ]);
    }
}
