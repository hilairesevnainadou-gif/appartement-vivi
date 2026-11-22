<?php

namespace App\Notifications\Concerns;

use App\Models\User;
use App\Notifications\Canaux\CanalPush;
use App\Notifications\Canaux\CanalSms;
use App\Services\NotificationsPush;
use App\Support\Parametres;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * Pour les notifications ordinaires (confirmation, rappel…) :
 *  - toujours gardées dans l'espace « Notifications » du site et de l'application (base de données, format « vivi ») ;
 *  - affichées sur le téléphone (push Firebase) quand la personne a l'application et que Firebase est réglé ;
 *  - envoyées en plus par SMS et/ou par email selon la page Paramètres, et seulement sur les canaux que le
 *    destinataire possède (un client sans email ne reçoit que le SMS).
 * Le contenu de l'espace Notifications est repris de l'email : titre = sujet, texte = premières lignes, bouton = action.
 *
 * Pour l'application, chaque notification porte aussi une catégorie (reservation, paiement, offre, logement, compte)
 * et une cible : l'écran à ouvrir quand on la touche (voir docs/API-mobile.md, « Notifications »).
 */
trait CanauxSelonParametres
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $canaux = $notifiable instanceof User && $notifiable->exists ? ['database'] : [];

        if ($canaux && app(NotificationsPush::class)->actif() && $notifiable->appareils()->exists()) {
            $canaux[] = CanalPush::class;
        }
        if ($this->parEmailEtSms() && Parametres::get('notifications_email') && $notifiable->routeNotificationFor('mail', $this)) {
            $canaux[] = 'mail';
        }
        if ($this->parEmailEtSms() && Parametres::get('notifications_sms') && $notifiable->routeNotificationFor('sms', $this)) {
            $canaux[] = CanalSms::class;
        }

        return $canaux;
    }

    /** Une notification peut se limiter à l'espace Notifications et au push (les offres, par exemple). */
    protected function parEmailEtSms(): bool
    {
        return true;
    }

    /** Espace Notifications du site (le format « vivi » le distingue des notifications de l'administration). */
    public function toDatabase(object $notifiable): array
    {
        /** @var MailMessage $mail */
        $mail = $this->toMail($notifiable);
        $sansMarkdown = fn (string $t) => trim(preg_replace(['/\*\*(.+?)\*\*/', '/\[(.+?)\]\((.+?)\)/'], ['$1', '$1'], $t));

        return [
            'format' => 'vivi',
            'titre' => (string) $mail->subject,
            'texte' => Str::limit(collect($mail->introLines)->map($sansMarkdown)->implode(' '), 400),
            'lien' => $mail->actionUrl,
            'action' => $mail->actionText,
            'icone' => $this->iconeNotification(),
            'categorie' => $this->categorieNotification(),
            'cible' => $this->cibleNotification(),
        ];
    }

    /** Icône Bootstrap Icons affichée dans l'espace Notifications. */
    protected function iconeNotification(): string
    {
        return match (class_basename($this)) {
            'ReservationConfirmee' => 'bi-check-circle',
            'NouvelleReservation' => 'bi-calendar-plus',
            'ReservationAnnulee' => 'bi-x-circle',
            'ReservationReportee', 'ReservationDeplacee' => 'bi-arrow-repeat',
            'RappelArrivee' => 'bi-alarm',
            'SejourCommence' => 'bi-door-open',
            'DemandeAvis' => 'bi-star',
            'RemboursementVerse' => 'bi-cash-coin',
            'RetraitTraite' => 'bi-wallet2',
            'CommissionAgent' => 'bi-graph-up-arrow',
            'BonusRecu' => 'bi-gift',
            'OffreVivi' => 'bi-tag',
            'DecisionLogement' => 'bi-house-check',
            'GerantViviDesigne' => 'bi-person-check',
            'GestionConfiee' => 'bi-person-badge',
            'AgentDesigne' => 'bi-megaphone',
            'Bienvenue' => 'bi-emoji-smile',
            default => 'bi-bell',
        };
    }

    /** Famille de la notification, pour les filtres et les canaux Android : reservation, paiement, offre, logement, compte. */
    protected function categorieNotification(): string
    {
        return match (class_basename($this)) {
            'ReservationConfirmee', 'NouvelleReservation', 'ReservationAnnulee', 'ReservationReportee', 'ReservationDeplacee',
            'RappelArrivee', 'SejourCommence', 'DemandeAvis' => 'reservation',
            'RemboursementVerse', 'RetraitTraite', 'CommissionAgent' => 'paiement',
            'BonusRecu', 'OffreVivi' => 'offre',
            'DecisionLogement', 'GerantViviDesigne' => 'logement',
            default => 'compte',
        };
    }

    /**
     * Écran de l'application à ouvrir : {ecran, reference?, id?}.
     * Écrans : reservation (billet du client), pro_reservation, avis, parrainage, solde, agent, pro_logement,
     * offre, accueil, notifications.
     *
     * @return array{ecran: string, reference?: string, id?: int}
     */
    protected function cibleNotification(): array
    {
        $reservation = property_exists($this, 'reservation') ? $this->reservation : null;
        $pourPro = property_exists($this, 'pourProprietaire') && $this->pourProprietaire;

        return match (true) {
            class_basename($this) === 'NouvelleReservation', $reservation && $pourPro => ['ecran' => 'pro_reservation', 'reference' => $reservation->reference],
            class_basename($this) === 'DemandeAvis' => ['ecran' => 'avis', 'reference' => $reservation->reference],
            $reservation !== null => ['ecran' => 'reservation', 'reference' => $reservation->reference],
            class_basename($this) === 'BonusRecu' => ['ecran' => 'parrainage'],
            class_basename($this) === 'AgentDesigne' => ['ecran' => 'agent'],
            class_basename($this) === 'GestionConfiee' => ['ecran' => 'pro_accueil'],
            default => ['ecran' => 'notifications'],
        };
    }
}
