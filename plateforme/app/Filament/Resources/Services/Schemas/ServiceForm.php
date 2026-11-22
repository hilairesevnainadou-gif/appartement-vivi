<?php

namespace App\Filament\Resources\Services\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('nom')->label('Nom')->required()->maxLength(120),
                        TextInput::make('icone')->label('Icône')->placeholder('bi-cup-straw')
                            ->helperText('Nom d’icône Bootstrap Icons, affichée sur le site.'),
                        Textarea::make('description')->label('Description')->rows(2)->columnSpanFull(),
                        TextInput::make('prix')->label('Prix')->integer()->minValue(0)->suffix('FCFA')->required(),
                        TextInput::make('unite')->label('Unité')->placeholder('le pack, par personne, le trajet…')->default('la prestation')->required(),
                        Toggle::make('actif')->label('Proposé aux propriétaires')->default(true),
                        TextInput::make('ordre')->label('Ordre d’affichage')->integer()->default(0),
                    ]),
            ]);
    }
}
