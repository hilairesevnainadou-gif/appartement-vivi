<?php

namespace App\Notifications\Canaux;

use App\Sms\Sms;
use Illuminate\Notifications\Notification;

/**
 * Canal de notification « sms » : la notification fournit toSms(), le destinataire
 * fournit son numéro via routeNotificationForSms().
 */
class CanalSms
{
    public function __construct(private readonly Sms $sms) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $numero = $notifiable->routeNotificationFor('sms', $notification);

        if (! $numero || ! method_exists($notification, 'toSms')) {
            return;
        }

        $this->sms->envoyer($numero, $notification->toSms($notifiable));
    }
}
