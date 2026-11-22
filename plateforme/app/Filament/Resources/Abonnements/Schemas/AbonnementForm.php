<?php

namespace App\Filament\Resources\Abonnements\Schemas;

use App\Enums\Role;
use App\Enums\StatutAbonnement;
use App\Models\FormuleAbonnement;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AbonnementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('proprietaire_id')
                            ->label('Propriétaire')
                            ->relationship('proprietaire', 'name', fn (Builder $query) => $query->role(Role::Proprietaire->value))
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('formule_abonnement_id')
                            ->label('Formule')
                            ->relationship('formule', 'nom', fn (Builder $query) => $query->where('actif', true)->orderBy('ordre'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                $set('prix_paye', FormuleAbonnement::find($state)?->prix_mensuel);
                            }),
                        DatePicker::make('debut')
                            ->label('Début')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(today())
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($state, Set $set) => $set('fin', $state ? Carbon::parse($state)->addMonth()->subDay()->toDateString() : null)),
                        DatePicker::make('fin')
                            ->label('Fin (incluse)')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(today()->addMonth()->subDay())
                            ->required()
                            ->afterOrEqual('debut'),
                        TextInput::make('prix_paye')
                            ->label('Prix payé')
                            ->integer()
                            ->minValue(0)
                            ->suffix('FCFA')
                            ->required()
                            ->helperText(fn (Get $get) => $get('formule_abonnement_id') ? 'Prix de la formule repris automatiquement ; modifiable.' : null),
                        Select::make('statut')
                            ->label('Statut')
                            ->options(StatutAbonnement::class)
                            ->default(StatutAbonnement::Actif)
                            ->required(),
                    ]),
            ]);
    }
}
