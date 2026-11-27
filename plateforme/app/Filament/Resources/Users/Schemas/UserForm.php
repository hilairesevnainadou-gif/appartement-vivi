<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Role;
use App\Services\Caisse;
use App\Services\ModeleEconomique;
use App\Support\Parametres;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Identité et contact')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->label('Nom et prénom')->required()->maxLength(255)->columnSpanFull(),
                        TextInput::make('telephone')
                            ->label('Téléphone')
                            ->tel()
                            ->prefix('+229')
                            ->helperText('Sert à la connexion par code SMS des clients et propriétaires.')
                            ->unique(ignoreRecord: true)
                            ->requiredWithout('email'),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->unique(ignoreRecord: true)
                            ->requiredWithout('telephone'),
                        Toggle::make('actif')->label('Compte actif')->default(true)->inline(false)
                            ->helperText('Un compte inactif ne peut plus se connecter.'),
                    ]),
                Section::make('Rôles et accès')
                    ->schema([
                        CheckboxList::make('roles')
                            ->label('Rôles')
                            ->relationship('roles', 'name')
                            ->getOptionLabelFromRecordUsing(fn (Model $record) => Role::tryFrom($record->name)?->libelle() ?? $record->name)
                            ->live()
                            ->required(),
                        TextInput::make('password')
                            ->label('Mot de passe')
                            ->password()
                            ->revealable()
                            ->minLength(10)
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->helperText(fn (string $operation) => $operation === 'edit'
                                ? 'Laisser vide pour garder le mot de passe actuel. Nécessaire seulement pour l’administration (/admin).'
                                : 'Nécessaire seulement pour l’administration (/admin) : administrateurs et caissières.'),
                    ]),
                // Exception à la règle générale (Paramètres › Rémunération par les propriétaires)
                Section::make('Rémunération de VIVI')
                    ->description(fn () => 'Règle générale actuelle : '.mb_strtolower(ModeleEconomique::options()[ModeleEconomique::defaut()])
                        .(ModeleEconomique::defaut() === ModeleEconomique::COMMISSION ? ' ('.Caisse::taux((float) Parametres::get('commission_retrait_taux')).')' : '').'.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible(fn (?Model $record) => $record?->hasRole(Role::Proprietaire->value))
                    ->schema([
                        Select::make('modele_economique')
                            ->label('Modèle de ce propriétaire')
                            ->options(ModeleEconomique::options())
                            ->placeholder('Règle générale')
                            ->live()
                            ->helperText('Abonnement : il paie une formule, ses retraits sont versés en entier. Commission : pas de formule, VIVI garde un pourcentage de chaque retrait.'),
                        TextInput::make('commission_retrait_taux')
                            ->label('Taux de commission personnalisé')
                            ->numeric()->minValue(0)->maxValue(100)->step(0.5)->suffix('%')
                            ->placeholder(fn () => 'Général : '.Caisse::taux((float) Parametres::get('commission_retrait_taux')))
                            ->helperText('Vide : taux général. Utilisé seulement quand il est sur commission.')
                            ->visible(fn ($get) => $get('modele_economique') !== ModeleEconomique::ABONNEMENT
                                && ($get('modele_economique') === ModeleEconomique::COMMISSION || ModeleEconomique::defaut() === ModeleEconomique::COMMISSION)),
                    ]),
            ]);
    }
}
