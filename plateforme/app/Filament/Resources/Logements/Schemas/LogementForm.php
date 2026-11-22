<?php

namespace App\Filament\Resources\Logements\Schemas;

use App\Enums\Role;
use App\Enums\StatutLogement;
use App\Enums\TypeLogement;
use App\Models\Service;
use App\Support\Parametres;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class LogementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Présentation')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('proprietaire_id')
                            ->label('Propriétaire')
                            ->relationship('proprietaire', 'name', fn (Builder $query) => $query->role(Role::Proprietaire->value))
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('type')
                            ->label('Type')
                            ->options([
                                'Logements' => collect(TypeLogement::logements())->mapWithKeys(fn ($t) => [$t->value => $t->getLabel()])->all(),
                                'Salles' => collect(TypeLogement::salles())->mapWithKeys(fn ($t) => [$t->value => $t->getLabel()])->all(),
                            ])
                            ->live()
                            ->required(),
                        TextInput::make('titre')
                            ->label('Nom du logement')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Set $set, Get $get, string $operation) {
                                if ($operation === 'create' || blank($get('slug'))) {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Adresse web (slug)')
                            ->helperText('Utilisé dans l’adresse de la fiche : /logements/studio-lagune')
                            ->required()
                            ->alphaDash()
                            ->unique(ignoreRecord: true),
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),

                Section::make('Emplacement')
                    ->columns(2)
                    ->schema([
                        Select::make('ville')
                            ->label('Ville')
                            ->options(fn () => array_combine(Parametres::get('villes'), Parametres::get('villes')))
                            ->required(),
                        TextInput::make('quartier')
                            ->label('Quartier')
                            ->required(),
                        TextInput::make('adresse')
                            ->label('Adresse exacte')
                            ->helperText('Communiquée au client seulement après le paiement.')
                            ->columnSpanFull(),
                        // Carte : « Utiliser ma position » ou clic ; remplit latitude, longitude et propose l'adresse
                        \Filament\Schemas\Components\View::make('filament.carte-logement')
                            ->viewData(['villes' => Parametres::get('villes')])
                            ->columnSpanFull(),
                        TextInput::make('latitude')->label('Latitude')->numeric()->live(),
                        TextInput::make('longitude')->label('Longitude')->numeric()->live(),
                    ]),

                // Gestion : choisie par le propriétaire ; le gérant VIVI se désigne avec le bouton en haut de la fiche
                Section::make('Gestion')
                    ->columns(2)
                    ->schema([
                        Select::make('mode_gestion')->label('Qui accueille les clients ?')
                            ->options(\App\Enums\ModeGestion::class)->default(\App\Enums\ModeGestion::Proprietaire)->required()->live(),
                        Select::make('gerant_vivi_id')->label('Gérant VIVI')
                            ->relationship('gerantVivi', 'name')
                            ->disabled()->dehydrated(false)
                            ->placeholder('À désigner (bouton « Désigner le gérant VIVI »)'),
                        // Contrat : pourcentage ou prix fixe, seulement si la gestion est confiée à VIVI
                        ...ContratGestion::champs(fn (Get $get) => in_array($get('mode_gestion'), [\App\Enums\ModeGestion::Vivi, \App\Enums\ModeGestion::Vivi->value], true)),
                    ]),

                Section::make('Capacité et préparation')
                    ->columns(2)
                    ->schema([
                        TextInput::make('capacite')
                            ->label(fn (Get $get) => self::salle($get) ? 'Capacité (personnes)' : 'Voyageurs')
                            ->integer()->minValue(1)
                            ->maxValue(fn (Get $get) => self::salle($get) ? TypeLogement::CAPACITE_MAX_SALLE : TypeLogement::CAPACITE_MAX_LOGEMENT)
                            ->default(2)->required(),
                        // Une salle n'a ni chambres ni lits et se loue d'un seul tenant : champs masqués, valeurs à 0 / 1
                        TextInput::make('chambres')->label('Chambres')->integer()->minValue(0)->maxValue(20)->default(1)->required()
                            ->hidden(fn (Get $get) => self::salle($get))->dehydratedWhenHidden()->dehydrateStateUsing(fn ($state, Get $get) => self::salle($get) ? 0 : $state),
                        TextInput::make('unites')->label('Chambres identiques louées séparément')->integer()->minValue(1)->maxValue(200)->default(1)->required()
                            ->helperText('1 : logement loué en entier. Résidence : réservable tant qu’une chambre est libre.')
                            ->hidden(fn (Get $get) => self::salle($get))->dehydratedWhenHidden()->dehydrateStateUsing(fn ($state, Get $get) => self::salle($get) ? 1 : $state),
                        TextInput::make('lits')->label('Lits')->integer()->minValue(0)->maxValue(20)->default(1)->required()
                            ->hidden(fn (Get $get) => self::salle($get))->dehydratedWhenHidden()->dehydrateStateUsing(fn ($state, Get $get) => self::salle($get) ? 0 : $state),
                        TextInput::make('salles_de_bain')->label('Salles de bain')->integer()->minValue(0)->maxValue(10)->default(1)->required(),
                        Select::make('temps_preparation_minutes')
                            ->label('Temps de préparation entre deux réservations')
                            ->options([0 => 'Aucun', 30 => '30 minutes', 60 => '1 heure', 120 => '2 heures'])
                            ->default(60)
                            ->required()
                            ->columnSpanFull(),
                    ]),

                Section::make('Équipements')
                    ->schema([
                        CheckboxList::make('equipements')
                            ->hiddenLabel()
                            ->relationship('equipements', 'nom', fn (Builder $query) => $query->orderBy('ordre'))
                            ->columns(3),
                    ]),

                Section::make('Services à la carte assurés dans ce logement')
                    ->description('Prix fixés par VIVI, dans le catalogue des services.')
                    ->schema([
                        CheckboxList::make('services')
                            ->hiddenLabel()
                            ->relationship('services', 'nom', fn (Builder $query) => $query->where('actif', true)->orderBy('ordre'))
                            ->columns(2)
                            ->live(),
                        // Par défaut le prix des services revient à VIVI ; exception pour ceux que le propriétaire assure lui-même
                        CheckboxList::make('services_proprietaire')
                            ->label('Services assurés par le propriétaire : leur prix lui revient')
                            ->helperText(fn () => 'Les autres services de ce logement reviennent à '
                                .(Parametres::get('services_beneficiaire') === 'vivi' ? 'VIVI' : 'au propriétaire').' (Administration › Paramètres).')
                            ->options(fn ($get) => Service::whereIn('id', (array) $get('services'))->orderBy('ordre')->pluck('nom', 'id')->all())
                            ->visible(fn ($get) => filled($get('services')))
                            ->columns(2)
                            ->afterStateHydrated(fn ($component, $record) => $component->state(
                                $record ? $record->services()->wherePivot('beneficiaire', 'proprietaire')->pluck('services.id')->map(fn ($id) => (string) $id)->all() : []))
                            ->dehydrated(false)
                            ->saveRelationshipsUsing(function ($record, $state) {
                                $choisis = array_map('intval', (array) $state);
                                foreach ($record->services()->get() as $service) {
                                    $record->services()->updateExistingPivot($service->id, [
                                        'beneficiaire' => in_array($service->id, $choisis, true) ? 'proprietaire' : null,
                                    ]);
                                }
                            }),
                    ]),

                Section::make('Publication')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('statut')
                            ->label('Statut')
                            ->options(StatutLogement::class)
                            ->default(StatutLogement::Brouillon)
                            ->required()
                            ->live(),
                        Toggle::make('en_ligne')
                            ->label('Visible dans la recherche')
                            ->helperText('Pause décidée par le propriétaire.')
                            ->default(true)
                            ->inline(false),
                        Textarea::make('motif_refus')
                            ->label('Motif du refus ou de la suspension')
                            ->rows(2)
                            ->columnSpanFull()
                            ->visible(fn (Get $get) => in_array(self::statut($get('statut')), [StatutLogement::Refuse, StatutLogement::Suspendu], true)),
                    ]),
            ]);
    }

    /** Le type choisi est-il une salle (fêtes, conférence) ? */
    private static function salle(Get $get): bool
    {
        $type = $get('type');

        return (bool) ($type instanceof TypeLogement ? $type : TypeLogement::tryFrom((string) $type))?->estSalle();
    }

    private static function statut(mixed $valeur): ?StatutLogement
    {
        return $valeur instanceof StatutLogement ? $valeur : StatutLogement::tryFrom((string) $valeur);
    }
}
