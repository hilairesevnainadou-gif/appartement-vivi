<?php

use App\Services\Abonnements;
use App\Services\ApresVente;
use App\Services\Reservations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tâches automatiques
|--------------------------------------------------------------------------
| En production, une seule ligne cron suffit :
|   * * * * * cd /chemin/plateforme && php artisan schedule:run >> /dev/null 2>&1
*/

Artisan::command('vivi:liberer-creneaux', function (Reservations $reservations) {
    $n = $reservations->libererExpirees();
    $this->info($n ? $n.' créneau(x) libéré(s) : paiement non finalisé.' : 'Aucun créneau à libérer.');
})->purpose('Annule les réservations dont le paiement n’a pas été finalisé (créneau retenu expiré depuis plus d’une heure)');

Schedule::command('vivi:liberer-creneaux')->everyFiveMinutes()->withoutOverlapping();

Artisan::command('vivi:expirer-abonnements', function (Abonnements $abonnements) {
    $n = $abonnements->expirer();
    $this->info($n ? $n.' abonnement(s) expiré(s).' : 'Aucun abonnement à expirer.');
})->purpose('Marque comme expirés les abonnements arrivés à échéance (leurs logements sortent déjà de la recherche)');

Schedule::command('vivi:expirer-abonnements')->dailyAt('00:10');

Artisan::command('vivi:cloturer-sejours', function (ApresVente $apresVente) {
    ['terminees' => $t, 'consommees' => $c] = $apresVente->cloturer();
    $this->info("{$t} séjour(s) terminé(s), {$c} réservation(s) consommée(s) (client absent).");
})->purpose('Fin de créneau : séjours en cours → terminés (demande d’avis) ; clients jamais arrivés → réservations consommées');

Artisan::command('vivi:rappels', function (ApresVente $apresVente) {
    $this->info($apresVente->rappeler().' rappel(s) envoyé(s).');
})->purpose('Rappel la veille de l’arrivée : heure, adresse, QR Code et dernier délai de report');

Schedule::command('vivi:cloturer-sejours')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('vivi:rappels')->everyFifteenMinutes()->withoutOverlapping();

// Journal d'activité : purge des entrées de plus d'un an (config/activitylog.php)
Schedule::command('activitylog:clean')->dailyAt('03:30');

// Surveillance : base de données, tâches en échec, espace disque ; prouve aussi que les tâches planifiées tournent
Artisan::command('vivi:surveiller', function (\App\Services\Surveillance $surveillance) {
    $problemes = $surveillance->controler();
    $this->info($problemes ? 'Problèmes : '.implode(', ', $problemes) : 'Tout va bien.');
})->purpose('Contrôle de santé de la plateforme et alertes');
Schedule::command('vivi:surveiller')->everyFiveMinutes()->withoutOverlapping();
