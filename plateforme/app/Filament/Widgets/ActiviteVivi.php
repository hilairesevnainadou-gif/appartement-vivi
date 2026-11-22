<?php

namespace App\Filament\Widgets;

use App\Enums\StatutReservation;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/** Activité : réservations payées (colonnes) et commission VIVI (courbe), par jour. */
class ActiviteVivi extends ChartWidget
{
    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Activité';

    protected ?string $description = 'Réservations payées et revenus VIVI (commission, part marketing sans agent, gestion confiée), par jour d’arrivée.';

    public static function canView(): bool
    {
        return \App\Filament\Resources\Reservations\ReservationResource::canViewAny();
    }

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    public ?string $filter = '30';

    protected function getFilters(): ?array
    {
        return ['7' => '7 derniers jours', '30' => '30 derniers jours', '90' => '3 derniers mois'];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $jours = (int) ($this->filter ?: 30);
        $debut = CarbonImmutable::today()->subDays($jours - 1);

        $lignes = Reservation::whereIn('statut', [StatutReservation::Confirmee, StatutReservation::EnCours, StatutReservation::Terminee, StatutReservation::Consommee])
            ->where('debut', '>=', $debut)
            ->where('debut', '<', CarbonImmutable::today()->addDay())
            ->get(['debut', 'montant_commission', 'montant_marketing', 'remise_agent', 'agent_id', 'montant_gestion'])
            ->groupBy(fn (Reservation $r) => $r->debut->toDateString());

        // Revenus VIVI : commission + part marketing des réservations venues sans agent
        $etiquettes = $nombres = $commissions = [];
        for ($d = $debut; $d->lte(CarbonImmutable::today()); $d = $d->addDay()) {
            $jour = $lignes->get($d->toDateString(), collect());
            $etiquettes[] = $jours > 31 ? $d->translatedFormat('j M') : $d->translatedFormat('D j');
            $nombres[] = $jour->count();
            $commissions[] = (int) $jour->sum(fn (Reservation $r) => (int) $r->montant_commission + $r->partMarketingVivi() + (int) $r->montant_gestion);
        }

        return [
            'datasets' => [
                [
                    'type' => 'bar',
                    'label' => 'Réservations',
                    'data' => $nombres,
                    'backgroundColor' => '#136f6e',
                    'borderRadius' => 6,
                    'maxBarThickness' => 22,
                    'yAxisID' => 'y',
                    'order' => 2,
                ],
                [
                    'type' => 'line',
                    'label' => 'Revenus VIVI (FCFA)',
                    'data' => $commissions,
                    'borderColor' => '#f4ad4c',
                    'backgroundColor' => '#f4ad4c',
                    'borderWidth' => 2.5,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 4,
                    'tension' => 0.3,
                    'cubicInterpolationMode' => 'monotone',
                    'yAxisID' => 'y1',
                    'order' => 1,
                ],
            ],
            'labels' => $etiquettes,
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 16 } },
                    tooltip: {
                        callbacks: {
                            label: (c) => c.dataset.yAxisID === 'y1'
                                ? ' Revenus VIVI : ' + new Intl.NumberFormat('fr-FR').format(c.parsed.y) + ' FCFA'
                                : ' ' + c.parsed.y + ' réservation' + (c.parsed.y > 1 ? 's' : ''),
                        },
                    },
                },
                scales: {
                    x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 12 } },
                    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#e2e8e7' }, title: { display: true, text: 'Réservations' } },
                    y1: {
                        beginAtZero: true, position: 'right', grid: { display: false },
                        ticks: { callback: (v) => v >= 1000 ? (v / 1000) + ' k' : v },
                        title: { display: true, text: 'FCFA' },
                    },
                },
            }
        JS);
    }
}
