<?php

namespace App\Filament\Resources\Logements\RelationManagers;

use App\Enums\Formule;
use App\Support\Montant;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

/** Une ligne par formule proposée : heure, demi-journée, journée. */
class TarifsRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'tarifs';

    protected static ?string $title = 'Tarifs et horaires';

    protected static ?string $modelLabel = 'tarif';

    protected static ?string $pluralModelLabel = 'tarifs';

    public function form(Schema $schema): Schema
    {
        $est = fn (Formule $f) => fn (Get $get) => self::formule($get('formule')) === $f;

        return $schema
            ->columns(2)
            ->components([
                Select::make('formule')
                    ->label('Formule')
                    ->options(Formule::class)
                    ->required()
                    ->live()
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('logement_id', $this->getOwnerRecord()->getKey()))
                    ->validationMessages(['unique' => 'Ce logement a déjà un tarif pour cette formule.']),
                TextInput::make('prix')
                    ->label(fn (Get $get) => match (self::formule($get('formule'))) {
                        Formule::Heure => 'Prix par heure',
                        Formule::DemiJournee => 'Prix du créneau',
                        Formule::Journee => 'Prix par jour',
                        default => 'Prix',
                    })
                    ->integer()
                    ->minValue(0)
                    ->suffix('FCFA')
                    ->required(),
                TimePicker::make('heure_ouverture')->label('Ouverture')->seconds(false)->visible($est(Formule::Heure))->required($est(Formule::Heure)),
                TimePicker::make('heure_fermeture')->label('Fermeture')->helperText('Même heure que l’ouverture = ouvert 24 h/24. Avant l’ouverture = après minuit.')->seconds(false)->visible($est(Formule::Heure))->required($est(Formule::Heure)),
                Select::make('duree_minimum_heures')
                    ->label('Durée minimale')
                    ->options([1 => '1 heure', 2 => '2 heures', 3 => '3 heures', 4 => '4 heures'])
                    ->default(2)
                    ->visible($est(Formule::Heure))
                    ->required($est(Formule::Heure)),
                TimePicker::make('heure_arrivee')->label(fn () => $this->getOwnerRecord()->estSalle() ? 'Début' : 'Arrivée à partir de')->seconds(false)->visible($est(Formule::Journee))->required($est(Formule::Journee)),
                TimePicker::make('heure_depart')->label(fn () => $this->getOwnerRecord()->estSalle() ? 'Fin le même jour (avant le début = après minuit)' : 'Départ avant (le lendemain)')->seconds(false)->visible($est(Formule::Journee))->required($est(Formule::Journee)),
                Toggle::make('actif')->label('Formule proposée')->default(true)->inline(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('formule')
            ->description('Les créneaux de la demi-journée se règlent dans l’onglet « Créneaux ».')
            ->columns([
                TextColumn::make('formule')->label('Formule'),
                TextColumn::make('prix')->label('Prix')->formatStateUsing(fn ($state) => Montant::fcfa($state)),
                TextColumn::make('horaires')
                    ->label('Horaires')
                    ->state(fn ($record) => match ($record->formule) {
                        Formule::Heure => $record->horairesEnClair().' · minimum '.$record->duree_minimum_heures.' h',
                        Formule::Journee => $this->getOwnerRecord()->estSalle()
                            ? 'De '.substr($record->heure_arrivee, 0, 5).' à '.substr($record->heure_depart, 0, 5).' (même jour)'
                            : 'Arrivée '.substr($record->heure_arrivee, 0, 5).' · départ '.substr($record->heure_depart, 0, 5).' le lendemain',
                        default => 'Voir les créneaux',
                    }),
                IconColumn::make('actif')->label('Proposée')->boolean(),
            ])
            ->headerActions([CreateAction::make()->label('Ajouter une formule')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    private static function formule(mixed $valeur): ?Formule
    {
        return $valeur instanceof Formule ? $valeur : Formule::tryFrom((string) $valeur);
    }
}
