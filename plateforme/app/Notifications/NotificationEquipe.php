<?php

namespace App\Notifications;

use Filament\Actions\Action;
use Filament\Notifications\Notification as NotificationFilament;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * À l'équipe VIVI : un événement à traiter (logement à vérifier, retrait demandé, remboursement à verser,
 * avis à relire…). Uniquement dans la cloche de l'administration ; envoyé par App\Support\Equipe::prevenir().
 */
class NotificationEquipe extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $titre,
        public readonly string $texte,
        public readonly ?string $url = null,
        public readonly string $icone = 'heroicon-o-bell',
        public readonly string $couleur = 'primary',
        public readonly string $libelleAction = 'Ouvrir',
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return NotificationFilament::make()
            ->title($this->titre)
            ->body($this->texte)
            ->icon($this->icone)
            ->iconColor($this->couleur)
            ->actions($this->url ? [Action::make('ouvrir')->label($this->libelleAction)->url($this->url)->markAsRead()] : [])
            ->getDatabaseMessage();
    }
}
