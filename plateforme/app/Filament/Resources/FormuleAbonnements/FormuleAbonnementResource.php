<?php

namespace App\Filament\Resources\FormuleAbonnements;

use App\Enums\Role;
use App\Filament\Concerns\AccesParRole;
use App\Filament\Resources\FormuleAbonnements\Pages\ManageFormuleAbonnements;
use App\Models\FormuleAbonnement;
use App\Support\Montant;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Formules d'abonnement des propriétaires : nom, prix et limites paramétrés ici. */
class FormuleAbonnementResource extends Resource
{
    use AccesParRole;

    protected static ?string $model = FormuleAbonnement::class;

    protected static array $rolesLecture = [Role::Administrateur, Role::Caissiere];

    protected static ?string $modelLabel = 'formule d’abonnement';

    protected static ?string $pluralModelLabel = 'Formules d’abonnement';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationLabel = 'Formules d’abonnement';

    protected static ?string $recordTitleAttribute = 'nom';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Abonnements';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('nom')->label('Nom')->required()->maxLength(60),
                TextInput::make('prix_mensuel')->label('Prix par mois')->integer()->minValue(0)->suffix('FCFA')->required(),
                // Chaque formule inclut au moins 2 logements et 1 salle (fêtes, conférences…), comptés séparément
                TextInput::make('logements_max')->label('Logements publiés au maximum')->integer()->minValue(2)->default(2)->required()
                    ->helperText('Appartements, studios, chambres, résidences. 2 au minimum.'),
                TextInput::make('salles_max')->label('Salles publiées au maximum')->integer()->minValue(1)->default(1)->required()
                    ->helperText('Salles de fêtes, de conférence, de réunion, espaces en plein air ou complets. 1 au minimum.'),
                TextInput::make('gestionnaires_max')->label('Gestionnaires délégués au maximum')->integer()->minValue(0)->default(0)->required(),
                TagsInput::make('avantages')->label('Avantages affichés')->placeholder('Ajouter une ligne puis Entrée')->columnSpanFull(),
                Toggle::make('actif')->label('Proposée aux propriétaires')->default(true),
                TextInput::make('ordre')->label('Ordre d’affichage')->integer()->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['abonnements' => fn ($a) => $a->where('statut', 'actif')]))
            ->defaultSort('ordre')
            ->reorderable('ordre')
            ->columns([
                TextColumn::make('nom')->label('Formule'),
                TextColumn::make('prix_mensuel')->label('Prix par mois')->formatStateUsing(fn ($state) => Montant::fcfa($state)),
                TextColumn::make('logements_max')->label('Logements')->alignCenter(),
                TextColumn::make('salles_max')->label('Salles')->alignCenter(),
                TextColumn::make('gestionnaires_max')->label('Gestionnaires')->alignCenter(),
                TextColumn::make('abonnements_count')->label('Abonnés actifs')->alignCenter(),
                ToggleColumn::make('actif')->label('Proposée')->disabled(fn () => ! static::canCreate()),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFormuleAbonnements::route('/'),
        ];
    }
}
