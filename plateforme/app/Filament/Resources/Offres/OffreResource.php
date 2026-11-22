<?php

namespace App\Filament\Resources\Offres;

use App\Filament\Concerns\AccesParRole;
use App\Filament\Resources\Offres\Pages\ManageOffres;
use App\Models\Offre;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Offres et annonces envoyées aux utilisateurs : espace Notifications du site, de l'application et push sur
 * le téléphone. Une offre envoyée ne se modifie pas (les personnes l'ont déjà reçue).
 */
class OffreResource extends Resource
{
    use AccesParRole;

    protected static array $actionsInterdites = ['update', 'delete', 'deleteAny'];

    protected static ?string $model = Offre::class;

    protected static ?string $modelLabel = 'offre';

    protected static ?string $pluralModelLabel = 'Offres et annonces';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'titre';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Réservations';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('titre')->label('Titre (titre de la notification)')->required()->maxLength(80)
                    ->placeholder('-20 % ce week-end à Cotonou')->columnSpanFull(),
                Textarea::make('texte')->label('Message')->required()->maxLength(400)->rows(3)
                    ->placeholder('Réservez avant dimanche et profitez de -20 % sur les séjours de nuit.')->columnSpanFull(),
                Select::make('cible')->label('Destinataires')->options(Offre::CIBLES)->default('clients')->required()->native(false),
                TextInput::make('lien')->label('Lien (facultatif)')->url()->maxLength(500)
                    ->placeholder(url('/'))
                    ->helperText('Page de VIVI à ouvrir, par exemple un logement. Sans lien : la recherche.')
                    ->rule(fn () => function (string $attribut, $valeur, $echec) {
                        if ($valeur && ! str_starts_with($valeur, url('/'))) {
                            $echec('Indiquez une page de VIVI ('.url('/').'…).');
                        }
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('auteur'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('titre')->label('Offre')->searchable()->description(fn (Offre $o) => \Illuminate\Support\Str::limit($o->texte, 90)),
                TextColumn::make('cible')->label('Destinataires')->formatStateUsing(fn (string $state) => Offre::CIBLES[$state] ?? $state)->badge(),
                TextColumn::make('destinataires')->label('Personnes prévenues')->numeric()->alignCenter(),
                TextColumn::make('envoyee_le')->label('Envoyée le')->dateTime('d/m/Y H:i')->placeholder('Pas encore envoyée'),
                TextColumn::make('auteur.name')->label('Par')->color('gray'),
            ])
            ->emptyStateHeading('Aucune offre envoyée')
            ->emptyStateDescription('Une offre arrive dans l’espace Notifications et en notification sur le téléphone des personnes qui acceptent les offres.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOffres::route('/'),
        ];
    }
}
