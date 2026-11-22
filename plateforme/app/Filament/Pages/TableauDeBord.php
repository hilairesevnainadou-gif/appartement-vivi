<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard;
use Illuminate\Contracts\Support\Htmlable;

/** Tableau de bord de l'administration : salutation, chiffres clés, activité et files de travail. */
class TableauDeBord extends Dashboard
{
    protected static ?string $title = 'Tableau de bord';

    public function getHeading(): string|Htmlable
    {
        return 'Bonjour '.auth()->user()->prenom();
    }

    public function getSubheading(): ?string
    {
        return ucfirst(today()->translatedFormat('l j F Y')).' · voici l’activité de VIVI.';
    }

    /** 1 colonne sur mobile ; 2 sur grand écran : graphique et arrivées en pleine largeur, les deux files de validation côte à côte. */
    public function getColumns(): int|array
    {
        return ['default' => 1, 'lg' => 2];
    }
}
