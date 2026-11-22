<?php

namespace App\Filament\Resources\Publicites;

use App\Enums\EmplacementPub;
use App\Enums\Role;
use App\Filament\Concerns\AccesParRole;
use App\Filament\Resources\Publicites\Actions\EncaisserPublicite;
use App\Filament\Resources\Publicites\Pages\CreatePublicite;
use App\Filament\Resources\Publicites\Pages\EditPublicite;
use App\Filament\Resources\Publicites\Pages\ListPublicites;
use App\Filament\Resources\Publicites\RelationManagers\PaiementsRelationManager;
use App\Filament\Resources\Publicites\Widgets\ChiffresPublicite;
use App\Models\Publicite;
use App\Support\Montant;
use App\Support\Parametres;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Publicités affichées dans l'application Android, par espace et par période, avec leurs vues et leurs clics. */
class PubliciteResource extends Resource
{
    use AccesParRole;

    /** La caissière consulte la liste et encaisse (permission publicites.encaisser). */
    protected static array $rolesLecture = [Role::Administrateur, Role::Caissiere];

    protected static ?string $model = Publicite::class;

    protected static ?string $slug = 'publicites';

    protected static ?string $modelLabel = 'publicité';

    protected static ?string $pluralModelLabel = 'Publicités de l’application';

    protected static ?string $navigationLabel = 'Publicités (application)';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'titre';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static string|UnitEnum|null $navigationGroup = 'Réservations';

    protected static ?int $navigationSort = 21;

    private static function emplacement(Get $get): ?EmplacementPub
    {
        $e = $get('emplacement');

        return $e instanceof EmplacementPub ? $e : EmplacementPub::tryFrom((string) $e);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'xl' => 2])
            ->components([
                Group::make([
                    Section::make('Contenu')
                        ->columns(2)
                        ->schema([
                            TextInput::make('titre')->label('Titre')->required()->maxLength(80)->placeholder('Traiteur Saveurs : -10 % ce mois')->columnSpanFull(),
                            TextInput::make('annonceur')->label('Annonceur')->maxLength(80)->placeholder('Vide : VIVI'),
                            TextInput::make('bouton')->label('Texte du bouton')->maxLength(30)->placeholder('En savoir plus'),
                            TextInput::make('annonceur_contact')->label('Contact de l’annonceur')->maxLength(120)->placeholder('Téléphone ou email')
                                ->helperText('Jamais affiché dans l’application.')->columnSpanFull(),
                            TextInput::make('texte')->label('Texte court')->maxLength(160)->columnSpanFull(),
                            TextInput::make('lien')->label('Lien')->url()->startsWith(['https://', url('/')])->maxLength(500)->placeholder('https://…')
                                ->helperText('Site de l’annonceur (https), lien wa.me, ou page de VIVI. Vide : la publicité ne s’ouvre pas.')->columnSpanFull(),
                        ]),
                    Section::make('Diffusion')
                        ->columns(2)
                        ->schema([
                            DatePicker::make('debut')->label('Du')->native(false)->displayFormat('d/m/Y')->helperText('Vide : dès maintenant.'),
                            DatePicker::make('fin')->label('Au')->native(false)->displayFormat('d/m/Y')->afterOrEqual('debut')->helperText('Vide : sans date de fin.'),
                            Select::make('villes')->label('Villes')->multiple()
                                ->options(fn () => array_combine(Parametres::get('villes'), Parametres::get('villes')))
                                ->placeholder('Toutes')->helperText('Montrée seulement à qui cherche dans ces villes.')->columnSpanFull(),
                            TextInput::make('ordre')->label('Ordre')->integer()->minValue(0)->maxValue(999)->default(0)->helperText('Le plus petit passe en premier.'),
                            Toggle::make('actif')->label('En ligne')->default(true)->inline(false),
                        ]),
                ]),
                Group::make([
                    Section::make('Prix')
                        ->description('Les règlements de l’annonceur se saisissent avec le bouton « Encaisser ».')
                        ->schema([
                            TextInput::make('prix')->label('Prix facturé')->integer()->default(0)->maxValue(100000000)->suffix('FCFA')->required()
                                ->minValue(fn (?Publicite $record) => $record?->montantPaye() ?? 0)
                                ->validationMessages(['min' => 'Le prix ne peut pas être inférieur à ce qui est déjà encaissé.'])
                                ->helperText('0 : publicité gratuite ou de VIVI.'),
                        ]),
                    Section::make('Espace et image')
                        ->schema([
                            Select::make('emplacement')->label('Espace dans l’application')->options(EmplacementPub::class)->required()->live(),
                            FileUpload::make('image')->label('Image')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                ->disk('public')->directory('publicites')->maxSize(2048)->imageEditor()->required()
                                ->helperText(fn (Get $get) => ($e = self::emplacement($get))
                                    ? 'Taille conseillée : '.$e->tailleEnClair().'. JPEG, PNG ou WebP, 2 Mo au plus.'
                                    : 'JPEG, PNG ou WebP, 2 Mo au plus. Choisissez l’espace pour voir la taille conseillée.'),
                        ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withSum('jours', 'vues')->withSum('jours', 'clics')->withSum('paiements', 'montant'))
            ->defaultSort('id', 'desc')
            ->columns([
                ImageColumn::make('image')->label('Image')->disk('public')->imageHeight(48),
                TextColumn::make('titre')->label('Publicité')->searchable()->description(fn (Publicite $p) => $p->annonceur ?: 'VIVI')->wrap(),
                TextColumn::make('emplacement')->label('Espace')->badge()->color('primary'),
                TextColumn::make('etat')->label('État')->badge()->state(fn (Publicite $p) => $p->etatEnClair())
                    ->color(fn (Publicite $p) => ['en_ligne' => 'success', 'programmee' => 'info', 'terminee' => 'gray', 'pause' => 'warning'][$p->etat()])
                    ->description(fn (Publicite $p) => collect([
                        $p->debut ? 'du '.$p->debut->format('d/m/Y') : null,
                        $p->fin ? 'au '.$p->fin->format('d/m/Y') : null,
                    ])->filter()->implode(' ') ?: null),
                TextColumn::make('jours_sum_vues')->label('Vues')->numeric()->default(0)->alignEnd(),
                TextColumn::make('jours_sum_clics')->label('Clics')->numeric()->default(0)->alignEnd()
                    ->description(fn (Publicite $p) => $p->jours_sum_vues ? number_format($p->jours_sum_clics * 100 / $p->jours_sum_vues, 1, ',', '').' %' : null),
                TextColumn::make('prix')->label('Prix')->formatStateUsing(fn (int $state) => $state ? Montant::fcfa($state) : '—')->alignEnd()
                    ->description(fn (Publicite $p) => $p->etatReglement() === 'partiel' ? 'reste '.Montant::fcfa($p->resteAPayer()) : null),
                TextColumn::make('reglement')->label('Règlement')->badge()->state(fn (Publicite $p) => $p->etatReglementEnClair())
                    ->color(fn (Publicite $p) => ['gratuite' => 'gray', 'payee' => 'success', 'partiel' => 'warning', 'a_payer' => 'danger'][$p->etatReglement()]),
                ToggleColumn::make('actif')->label('En ligne')->disabled(fn () => ! static::canCreate()),
            ])
            ->filters([
                SelectFilter::make('emplacement')->label('Espace')->options(EmplacementPub::class),
                Filter::make('a_encaisser')->label('À encaisser')->query(fn ($query) => $query->aEncaisser()),
            ])
            ->recordActions([EncaisserPublicite::make(), EditAction::make()])
            ->emptyStateHeading('Aucune publicité')
            ->emptyStateDescription('Les publicités créées ici s’affichent dans l’application. Sans publicité, l’espace peut montrer une annonce AdMob (Paramètres).');
    }

    public static function getRelations(): array
    {
        return [PaiementsRelationManager::class];
    }

    public static function getWidgets(): array
    {
        return [ChiffresPublicite::class];
    }

    /** Une publicité déjà encaissée ne se supprime pas (comptabilité) : on la met en pause. */
    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return $record->paiements()->exists()
            ? Response::deny('Cette publicité a des règlements : mettez-la en pause.')
            : parent::getDeleteAuthorizationResponse($record);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPublicites::route('/'),
            'create' => CreatePublicite::route('/create'),
            'edit' => EditPublicite::route('/{record}/edit'),
        ];
    }
}
