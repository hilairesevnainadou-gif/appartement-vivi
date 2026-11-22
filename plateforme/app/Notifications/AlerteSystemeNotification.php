<?php

namespace App\Notifications;

use App\Filament\Resources\Alertes\AlerteResource;
use App\Models\AlerteSysteme;
use App\Notifications\Canaux\CanalSms;
use App\Support\Parametres;
use Filament\Actions\Action;
use Filament\Notifications\Notification as NotificationFilament;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aux administrateurs : incident détecté par la surveillance. Toujours dans la cloche de l'administration ;
 * par email si l'administrateur en a un ; par SMS en plus si l'alerte est critique.
 */
class AlerteSystemeNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly AlerteSysteme $alerte) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $canaux = ['database'];
        if ($notifiable->routeNotificationFor('mail', $this) && Parametres::get('notifications_email')) {
            $canaux[] = 'mail';
        }
        if ($this->alerte->niveau === AlerteSysteme::CRITIQUE && Parametres::get('notifications_sms') && $notifiable->routeNotificationFor('sms', $this)) {
            $canaux[] = CanalSms::class;
        }

        return $canaux;
    }

    /** Cloche de l'administration (notifications Filament enregistrées en base). */
    public function toDatabase(object $notifiable): array
    {
        $a = $this->alerte;

        return NotificationFilament::make()
            ->title(($a->niveau === AlerteSysteme::CRITIQUE ? 'Alerte critique : ' : 'Alerte : ').$a->titre)
            ->body($a->message.($a->source ? ' Source : '.$a->source.'.' : ''))
            ->icon($a->niveau === AlerteSysteme::CRITIQUE ? 'heroicon-o-shield-exclamation' : 'heroicon-o-exclamation-triangle')
            ->iconColor($a->niveau === AlerteSysteme::CRITIQUE ? 'danger' : 'warning')
            ->actions([Action::make('voir')->label('Voir l’alerte')->url(AlerteResource::getUrl('index'))->markAsRead()])
            ->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $a = $this->alerte;

        return (new MailMessage)
            ->subject('[VIVI] '.AlerteSysteme::libelleNiveau($a->niveau).' : '.$a->titre)
            ->greeting('Alerte de surveillance')
            ->line($a->message)
            ->line('Source : '.($a->source ?: '—').' · '.$a->occurrences.' occurrence(s) · '.$a->derniere_le?->format('d/m/Y H:i'))
            ->action('Ouvrir les alertes', AlerteResource::getUrl('index'))
            ->salutation('Surveillance VIVI');
    }

    public function toSms(object $notifiable): string
    {
        return 'VIVI ALERTE CRITIQUE : '.$this->alerte->titre.'. '.\Illuminate\Support\Str::limit($this->alerte->message, 110);
    }
}
