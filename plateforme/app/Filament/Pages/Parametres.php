<?php

namespace App\Filament\Pages;

use App\Enums\EmplacementPub;
use App\Enums\SourcePub;
use App\Support\Parametres as ParametresVivi;
use BackedEnum;
use Filament\Actions\Action;
use App\Models\FormuleAbonnement;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Réglages de la plateforme modifiables sans toucher au code.
 * Ils s'appliquent aux nouvelles réservations ; une réservation déjà payée
 * garde le taux de commission et le bénéficiaire en vigueur à sa création.
 */
class Parametres extends Page
{
    protected static ?string $title = 'Paramètres de la plateforme';

    protected static ?string $navigationLabel = 'Paramètres';

    protected static ?string $slug = 'parametres';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 2;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('parametres.gerer');
    }

    public function mount(): void
    {
        $this->form->fill(ParametresVivi::tous());
    }

    public function getSubheading(): ?string
    {
        return 'Réglages appliqués aux nouvelles réservations. Une réservation déjà payée garde les règles de sa création.';
    }

    /**
     * Même présentation que les autres fiches : cartes sur deux colonnes. Les deux colonnes sont indépendantes
     * (deux groupes), pour qu'une carte courte ne laisse pas de vide sous elle.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(['default' => 1, 'xl' => 2])
            ->components([
                Group::make([
                    $this->sectionCommission(),
                    $this->sectionAbonnements(),
                    $this->sectionContact(),
                ]),
                Group::make([
                    $this->sectionReservations(),
                    $this->sectionNotifications(),
                    $this->sectionParrainage(),
                    $this->sectionListes(),
                ]),
                $this->sectionPublicite(),
            ]);
    }

    private function sectionCommission(): Section
    {
        return Section::make('Commission de la plateforme')
            ->description('Payée par le client en plus du prix, sur chaque réservation. Affichée avant le paiement.')
            ->columns(2)
            ->schema([
                TextInput::make('commission_pourcentage')
                    ->label('Taux des logements')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(50)
                    ->step(0.5)
                    ->suffix('%')
                    ->required(),
                Select::make('services_beneficiaire')
                    ->label('Services à la carte versés à')
                    ->options(['vivi' => 'VIVI', 'proprietaire' => 'Propriétaire du logement'])
                    ->selectablePlaceholder(false)
                    ->helperText('Par défaut. Exception possible dans la fiche du logement.')
                    ->required(),
                // Salles : taux selon le montant de la réservation (location + services)
                Repeater::make('commission_salles_tranches')
                    ->label('Taux des salles, par tranches de montant')
                    ->helperText('Le taux de la tranche s’applique à toute la réservation (location + services). Laissez « Jusqu’à » vide pour la dernière tranche.')
                    ->table([
                        TableColumn::make('Jusqu’à'),
                        TableColumn::make('Taux'),
                    ])
                    ->extraAttributes(['class' => 'vivi-tranches'])
                    ->schema([
                        TextInput::make('jusqua')->label('Jusqu’à')->numeric()->minValue(0)->suffix('FCFA')->placeholder('Au-delà'),
                        TextInput::make('pourcentage')->label('Taux')->numeric()->minValue(0)->maxValue(50)->step(0.5)->suffix('%')->required(),
                    ])
                    ->defaultItems(3)
                    ->minItems(1)
                    ->maxItems(8)
                    ->reorderable(false)
                    ->addActionLabel('Ajouter une tranche')
                    ->addActionAlignment(Alignment::Start)
                    ->columnSpanFull(),
            ]);
    }

    private function sectionReservations(): Section
    {
        return Section::make('Réservations')
            ->description('Report d’une réservation et créneau retenu pendant le paiement.')
            ->columns(2)
            ->schema([
                TextInput::make('report_delai_minimum_heures')
                    ->label('Report impossible à moins de')
                    ->integer()->minValue(0)->maxValue(72)->suffix('heures')->required()
                    ->helperText('Passé ce délai, seuls le propriétaire ou VIVI la déplacent.'),
                TextInput::make('report_fenetre_jours')
                    ->label('Nouvelle date dans les')
                    ->integer()->minValue(1)->maxValue(60)->suffix('jours')->required()
                    ->helperText('Après la date prévue au départ.'),
                Select::make('blocage_creneau_minutes')
                    ->label('Créneau retenu pendant le paiement')
                    ->options([10 => '10 minutes', 15 => '15 minutes', 20 => '20 minutes', 30 => '30 minutes'])
                    ->columnSpanFull()
                    ->required(),
            ]);
    }

    private function sectionNotifications(): Section
    {
        return Section::make('Notifications')
            ->description('Confirmation, rappel, code de connexion… envoyés aux clients et aux propriétaires.')
            ->columns(2)
            ->schema([
                Toggle::make('notifications_sms')->label('Envoyer par SMS'),
                Toggle::make('notifications_email')->label('Envoyer par email'),
            ]);
    }

    private function sectionAbonnements(): Section
    {
        $essai = fn ($get) => (bool) $get('essai_actif');
        $promo = fn ($get) => (bool) $get('promo_actif');

        return Section::make('Abonnements : essai gratuit et promotion')
            ->description('Les prix barrés et les bandeaux s’affichent sur la page Propriétaires et dans l’espace abonnement.')
            ->columns(2)
            ->schema([
                Toggle::make('essai_actif')->label('Proposer un essai gratuit')->live()
                    ->helperText('Une seule fois par propriétaire, avant tout abonnement payé.')->columnSpanFull(),
                TextInput::make('essai_jours')->label('Durée de l’essai')->integer()->minValue(1)->maxValue(90)->suffix('jours')
                    ->visible($essai)->required($essai),
                Select::make('essai_formule_id')->label('Formule de l’essai')
                    ->options(fn () => [0 => 'Au choix du propriétaire'] + FormuleAbonnement::where('actif', true)->orderBy('ordre')->pluck('nom', 'id')->all())
                    ->visible($essai)->selectablePlaceholder(false),

                Toggle::make('promo_actif')->label('Remise sur les abonnements')->live()->columnSpanFull(),
                TextInput::make('promo_libelle')->label('Nom de l’offre')->maxLength(60)->placeholder('Offre de lancement')
                    ->visible($promo)->required($promo),
                TextInput::make('promo_pourcentage')->label('Remise')->integer()->minValue(1)->maxValue(100)->suffix('%')
                    ->visible($promo)->required($promo),
                DatePicker::make('promo_debut')->label('Du')->native(false)->displayFormat('d/m/Y')
                    ->helperText('Vide : dès maintenant.')->visible($promo),
                DatePicker::make('promo_fin')->label('Au')->native(false)->displayFormat('d/m/Y')->afterOrEqual('promo_debut')
                    ->helperText('Vide : sans date de fin.')->visible($promo),
                Toggle::make('promo_nouveaux_seulement')->label('Premier abonnement payé seulement')->columnSpanFull()
                    ->visible($promo),
            ]);
    }

    private function sectionParrainage(): Section
    {
        $actif = fn ($get) => (bool) $get('parrainage_actif');

        return Section::make('Parrainage et bonus')
            ->description('Au premier séjour de l’ami invité (QR Code scanné), le parrain reçoit un bonus à déduire d’une prochaine réservation. Financé par VIVI : le propriétaire reçoit sa location en entier.')
            ->columns(2)
            ->schema([
                Toggle::make('parrainage_actif')->label('Activer le parrainage')->live()->columnSpanFull()
                    ->helperText('Désactivé : plus de nouveaux bonus ; les bonus déjà reçus restent utilisables.'),
                TextInput::make('parrainage_bonus_parrain')->label('Bonus du parrain')->integer()->minValue(0)->maxValue(100000)->step(500)->suffix('FCFA')
                    ->helperText('Au premier séjour de chaque ami.')->visible($actif)->required($actif),
                TextInput::make('parrainage_bonus_filleul')->label('Bonus de bienvenue de l’ami')->integer()->minValue(0)->maxValue(100000)->step(500)->suffix('FCFA')
                    ->helperText('À la création du compte. 0 : aucun.')->visible($actif)->required($actif),
                TextInput::make('parrainage_validite_jours')->label('Validité d’un bonus')->integer()->minValue(7)->maxValue(730)->suffix('jours')
                    ->visible($actif)->required($actif),
                TextInput::make('bonus_plafond_pourcentage')->label('Part payable avec le bonus')->integer()->minValue(1)->maxValue(90)->suffix('%')
                    ->helperText('Au plus, sur le total. Le reste est payé par FedaPay.')->required(),
            ]);
    }

    private function sectionContact(): Section
    {
        return Section::make('Contact et pages légales')
            ->description('Affiché dans le pied de page du site, les billets et les pages d’aide. Sans numéro, le lien WhatsApp est masqué.')
            ->columns(2)
            ->schema([
                TextInput::make('whatsapp_numero')
                    ->label('Numéro WhatsApp')
                    ->tel()
                    ->placeholder('01 97 00 00 00')
                    ->regex('/^[+0-9 .\-()]{8,20}$/')
                    ->helperText('Numéro béninois : +229 est ajouté.'),
                TextInput::make('email_contact')
                    ->label('Email de contact')
                    ->email()
                    ->placeholder('contact@vivi.bj'),
                TextInput::make('whatsapp_message')
                    ->label('Message pré-rempli')
                    ->maxLength(200)
                    ->placeholder('Bonjour VIVI, j’ai une question :')
                    ->helperText('Déjà écrit quand le client ouvre WhatsApp ; il peut le modifier.')
                    ->columnSpanFull(),
                Toggle::make('confidentialite_publiee')->label('Publier la politique de confidentialité')
                    ->helperText(fn () => new \Illuminate\Support\HtmlString('À activer après relecture. <a href="'.e(route('aide.confidentialite')).'" target="_blank" style="text-decoration:underline">Lire la page</a>'))
                    ->columnSpanFull(),
            ]);
    }

    private function sectionListes(): Section
    {
        return Section::make('Listes')
            ->description('Villes proposées aux clients et modes de règlement proposés à la caissière.')
            ->schema([
                TagsInput::make('villes')
                    ->label('Villes ouvertes à la réservation')
                    ->placeholder('Ajouter une ville puis Entrée')
                    ->required(),
                TagsInput::make('modes_reglement')
                    ->label('Modes de règlement des retraits')
                    ->placeholder('Ajouter un mode puis Entrée')
                    ->required(),
            ]);
    }

    /** Publicité de l'application : pleine largeur, une ligne par espace. */
    private function sectionPublicite(): Section
    {
        $active = fn ($get) => (bool) $get('pub_active');
        $admob = fn ($get) => (bool) $get('pub_active') && (bool) $get('admob_actif');

        return Section::make('Publicité dans l’application')
            ->description('Affichée aux clients, jamais sur le paiement, le billet, le scanner ni dans les espaces professionnels. Vos publicités se créent dans « Publicités (application) ».')
            ->columnSpanFull()
            ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
            ->schema([
                Toggle::make('pub_active')->label('Afficher la publicité')->live()->columnSpanFull(),
                TextInput::make('pub_frequence_liste')->label('Dans les résultats, une toutes les')->integer()->minValue(3)->maxValue(30)->suffix('fiches')
                    ->visible($active)->required($active),
                TextInput::make('pub_plein_ecran_heures')->label('Plein écran, une toutes les')->integer()->minValue(1)->maxValue(168)->suffix('heures')
                    ->visible($active)->required($active),
                Toggle::make('admob_actif')->label('Annonces Google AdMob')->live()->inline(false)
                    ->visible($active),
                Toggle::make('admob_test')->label('Mode test AdMob')->inline(false)
                    ->helperText('À laisser activé jusqu’à la mise en ligne : les annonces de test ne rapportent rien.')
                    ->visible($admob),
                TextInput::make('admob_editeur')->label('Identifiant éditeur AdMob')->placeholder('pub-0000000000000000')
                    ->regex('/^pub-\d{16}$/')->validationMessages(['regex' => 'Format attendu : pub- suivi de 16 chiffres.'])
                    ->helperText('AdMob › Paramètres. Crée la page /app-ads.txt demandée par Google.')
                    ->visible($admob)->columnSpan(['md' => 2]),
                Grid::make(['default' => 1, 'md' => 2])->columnSpanFull()->visible($active)
                    ->schema(collect(EmplacementPub::cases())->flatMap(fn (EmplacementPub $e) => [
                        Select::make("pub_emplacements.{$e->value}.source")->label($e->getLabel())
                            ->options(SourcePub::class)->selectablePlaceholder(false)->required()
                            ->helperText('Image : '.$e->tailleEnClair()),
                        TextInput::make("pub_emplacements.{$e->value}.admob")->label('Bloc AdMob : '.$e->getLabel())
                            ->placeholder('ca-app-pub-0000000000000000/0000000000')
                            ->regex('#^ca-app-pub-\d{16}/\d{10}$#')->validationMessages(['regex' => 'Format attendu : ca-app-pub-…/… (bloc d’annonces, pas l’identifiant de l’application).'])
                            ->helperText('Format à choisir dans AdMob : '.$e->formatAdmobEnClair())
                            ->visible($admob),
                    ])->all()),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('enregistrer')
                ->footer([
                    // Barre collée en bas de l'écran, comme sur les autres formulaires
                    Actions::make([
                        Action::make('enregistrer')->label('Enregistrer')->submit('enregistrer')->keyBindings(['mod+s']),
                    ])->sticky($this->areFormActionsSticky())->key('form-actions'),
                ]),
        ]);
    }

    public function enregistrer(): void
    {
        $valeurs = $this->form->getState();
        if (isset($valeurs['pub_emplacements'])) {
            // Les blocs AdMob masqués (AdMob désactivé) ne sont pas dans le formulaire : on les garde
            $valeurs['pub_emplacements'] = array_replace_recursive(ParametresVivi::get('pub_emplacements'), $valeurs['pub_emplacements']);
        }
        ParametresVivi::enregistrer($valeurs);
        $this->form->fill(ParametresVivi::tous());

        Notification::make()
            ->title('Paramètres enregistrés')
            ->body('Ils s’appliquent aux prochaines réservations.')
            ->success()
            ->send();
    }
}
