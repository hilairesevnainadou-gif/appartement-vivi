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
use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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

/** Publicités de l'application Android et du site web : espaces, période, vues, clics et rapports envoyés à l'annonceur. */
class PubliciteResource extends Resource
{
    use AccesParRole;

    /** La caissière consulte la liste et encaisse (permission publicites.encaisser). */
    protected static array $rolesLecture = [Role::Administrateur, Role::Caissiere];

    protected static ?string $model = Publicite::class;

    protected static ?string $slug = 'publicites';

    protected static ?string $modelLabel = 'publicité';

    protected static ?string $pluralModelLabel = 'Publicités';

    protected static ?string $navigationLabel = 'Publicités (application et site)';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'titre';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static string|UnitEnum|null $navigationGroup = 'Réservations';

    protected static ?int $navigationSort = 21;

    private const FORMATS = ['banniere' => 'bannière', 'carte' => 'carte', 'plein_ecran' => 'plein écran'];

    /** Tailles d'image conseillées pour les espaces cochés (application et site). */
    private static function tailles(Get $get): string
    {
        $formats = collect($get('espaces_application') ?? [])->merge($get('espaces_site') ?? [])
            ->map(fn ($e) => EmplacementPub::tryFrom((string) $e))->filter()
            ->mapWithKeys(fn (EmplacementPub $e) => [$e->format() => self::FORMATS[$e->format()].' '.$e->tailleEnClair()]);

        return $formats->isEmpty()
            ? 'JPEG, PNG ou WebP, 2 Mo au plus. Cochez les espaces pour voir la taille conseillée.'
            : 'Taille conseillée : '.$formats->implode(' ; ').($formats->count() > 1 ? ' (l’image est recadrée au centre selon l’espace : gardez le message au milieu)' : '').'. JPEG, PNG ou WebP, 2 Mo au plus.';
    }

    /** Recherche d'un compte VIVI (nom, email ou téléphone) pour l'annonceur. */
    private static function chercherComptes(string $texte): array
    {
        $texte = trim($texte);

        return User::query()
            ->where(fn ($q) => $q->where('name', 'like', "%{$texte}%")->orWhere('email', 'like', "%{$texte}%")->orWhere('telephone', 'like', "%{$texte}%"))
            ->orderBy('name')->limit(20)->get()
            ->mapWithKeys(fn (User $u) => [$u->id => self::libelleCompte($u)])->all();
    }

    private static function libelleCompte(User $u): string
    {
        return $u->name.' · '.collect([$u->email, $u->telephone])->filter()->implode(' · ');
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
                            TextInput::make('bouton')->label('Texte du bouton')->maxLength(30)->placeholder('En savoir plus')->columnSpanFull(),
                            TextInput::make('texte')->label('Texte court')->maxLength(160)->columnSpanFull(),
                            TextInput::make('lien')->label('Lien')->url()->startsWith(['https://', url('/')])->maxLength(500)->placeholder('https://…')
                                ->helperText('Site de l’annonceur (https), lien wa.me, ou page de VIVI. Vide : la publicité ne s’ouvre pas.')->columnSpanFull(),
                        ]),
                    Section::make('Où afficher la publicité')
                        ->description('Cochez un ou plusieurs espaces, dans l’application et/ou sur le site web. Visible par tous, connectés ou non.')
                        ->columns(['default' => 1, 'md' => 2])
                        ->schema([
                            CheckboxList::make('espaces_application')->label('Dans l’application Android')
                                ->options(collect(EmplacementPub::cases())->mapWithKeys(fn (EmplacementPub $e) => [$e->value => $e->getLabel()])->all())
                                ->descriptions(collect(EmplacementPub::cases())->mapWithKeys(fn (EmplacementPub $e) => [$e->value => ucfirst(self::FORMATS[$e->format()]).', '.$e->tailleEnClair()])->all())
                                ->bulkToggleable()->live()->default([])
                                ->required(fn (Get $get) => empty($get('espaces_site')))
                                ->validationMessages(['required' => 'Cochez au moins un espace, dans l’application ou sur le site.']),
                            CheckboxList::make('espaces_site')->label('Sur le site web')
                                ->options(EmplacementPub::optionsSite())
                                ->bulkToggleable()->live()->default([])
                                ->helperText('Seules les publicités de VIVI s’affichent sur le site (pas d’annonces AdMob).'),
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
                    Section::make('Annonceur et rapports')
                        ->description('Choisissez le compte de l’annonceur sur VIVI ou saisissez son email : il reçoit automatiquement ses vues et ses clics.')
                        ->columns(2)
                        ->schema([
                            Select::make('annonceur_id')->label('Compte de l’annonceur sur VIVI')->searchable()->nullable()->columnSpanFull()
                                ->getSearchResultsUsing(fn (string $search) => self::chercherComptes($search))
                                ->getOptionLabelUsing(fn ($value) => ($u = User::find($value)) ? self::libelleCompte($u) : null)
                                ->placeholder('Nom, email ou téléphone d’un client ou d’un utilisateur')
                                ->helperText('Facultatif. Il reçoit ses rapports par email et dans son espace Notifications.')
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                    if ($state && ($u = User::find($state))) {
                                        if (! $get('annonceur')) {
                                            $set('annonceur', $u->name);
                                        }
                                        if (! $get('annonceur_contact')) {
                                            $set('annonceur_contact', $u->telephone ?: $u->email);
                                        }
                                    }
                                }),
                            TextInput::make('annonceur')->label('Nom affiché')->maxLength(80)->placeholder('Vide : VIVI'),
                            TextInput::make('annonceur_contact')->label('Contact de l’annonceur')->maxLength(120)->placeholder('Téléphone ou email')
                                ->helperText('Jamais affiché aux clients.'),
                            TextInput::make('rapport_email')->label('Email pour les rapports')->email()->maxLength(190)->columnSpanFull()
                                ->placeholder(fn (Get $get) => ($u = User::find($get('annonceur_id'))) && $u->email ? 'Vide : '.$u->email : 'annonceur@exemple.com')
                                ->helperText('Vide : l’email du compte choisi ci-dessus. Rempli : les rapports partent aussi à cette adresse.'),
                            Select::make('rapport_frequence')->label('Envoi automatique des rapports')->options(Publicite::RAPPORTS)
                                ->default('hebdomadaire')->required()->selectablePlaceholder(false)->columnSpanFull()
                                ->helperText(fn (?Publicite $record) => 'Sauf « Jamais », un bilan final part aussi le lendemain de la date de fin.'
                                    .($record?->rapport_envoye_le ? ' Dernier envoi : '.$record->rapport_envoye_le->format('d/m/Y à H:i').'.' : '')),
                        ]),
                    Section::make('Image')
                        ->schema([
                            FileUpload::make('image')->label('Image')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                ->disk('public')->directory('publicites')->maxSize(2048)->imageEditor()->required()
                                ->helperText(fn (Get $get) => self::tailles($get)),
                        ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withSum('jours', 'vues')->withSum('jours', 'clics')->withSum('jours', 'vues_site')->withSum('jours', 'clics_site')
                ->withSum('paiements', 'montant'))
            ->defaultSort('id', 'desc')
            ->columns([
                ImageColumn::make('image')->label('Image')->disk('public')->imageHeight(48),
                TextColumn::make('titre')->label('Publicité')->searchable()->description(fn (Publicite $p) => $p->annonceur ?: 'VIVI')->wrap(),
                TextColumn::make('espaces')->label('Espaces')->state(fn (Publicite $p) => $p->espacesEnClair())->wrap()
                    ->description(fn (Publicite $p) => $p->rapport_frequence !== 'aucun' && $p->destinatairesRapport()
                        ? 'Rapports : '.mb_strtolower(Publicite::RAPPORTS[$p->rapport_frequence] ?? '') : null),
                TextColumn::make('etat')->label('État')->badge()->state(fn (Publicite $p) => $p->etatEnClair())
                    ->color(fn (Publicite $p) => ['en_ligne' => 'success', 'programmee' => 'info', 'terminee' => 'gray', 'pause' => 'warning'][$p->etat()])
                    ->description(fn (Publicite $p) => collect([
                        $p->debut ? 'du '.$p->debut->format('d/m/Y') : null,
                        $p->fin ? 'au '.$p->fin->format('d/m/Y') : null,
                    ])->filter()->implode(' ') ?: null),
                TextColumn::make('vues')->label('Vues')->numeric()->alignEnd()
                    ->state(fn (Publicite $p) => (int) $p->jours_sum_vues + (int) $p->jours_sum_vues_site)
                    ->description(fn (Publicite $p) => $p->jours_sum_vues_site ? 'dont '.number_format((int) $p->jours_sum_vues_site, 0, ',', ' ').' site' : null),
                TextColumn::make('clics')->label('Clics')->numeric()->alignEnd()
                    ->state(fn (Publicite $p) => (int) $p->jours_sum_clics + (int) $p->jours_sum_clics_site)
                    ->description(function (Publicite $p) {
                        $vues = (int) $p->jours_sum_vues + (int) $p->jours_sum_vues_site;

                        return $vues ? number_format(((int) $p->jours_sum_clics + (int) $p->jours_sum_clics_site) * 100 / $vues, 1, ',', '').' %' : null;
                    }),
                TextColumn::make('prix')->label('Prix')->formatStateUsing(fn (int $state) => $state ? Montant::fcfa($state) : '—')->alignEnd()
                    ->description(fn (Publicite $p) => $p->etatReglement() === 'partiel' ? 'reste '.Montant::fcfa($p->resteAPayer()) : null),
                TextColumn::make('reglement')->label('Règlement')->badge()->state(fn (Publicite $p) => $p->etatReglementEnClair())
                    ->color(fn (Publicite $p) => ['gratuite' => 'gray', 'payee' => 'success', 'partiel' => 'warning', 'a_payer' => 'danger'][$p->etatReglement()]),
                ToggleColumn::make('actif')->label('En ligne')->disabled(fn () => ! static::canCreate()),
            ])
            ->filters([
                SelectFilter::make('espace')->label('Espace')->options(EmplacementPub::class)
                    ->query(fn ($query, array $data) => $data['value']
                        ? $query->where(fn ($q) => $q->whereJsonContains('espaces_application', $data['value'])->orWhereJsonContains('espaces_site', $data['value']))
                        : $query),
                Filter::make('a_encaisser')->label('À encaisser')->query(fn ($query) => $query->aEncaisser()),
            ])
            ->recordActions([EncaisserPublicite::make(), EditAction::make()])
            ->emptyStateHeading('Aucune publicité')
            ->emptyStateDescription('Les publicités créées ici s’affichent dans l’application et sur le site web. Sans publicité, un espace de l’application peut montrer une annonce AdMob (Paramètres).');
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
