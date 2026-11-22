<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Role;
use Filament\Forms\Components\CheckboxList;
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
            ]);
    }
}
