<?php

namespace App\Filament\Resources\Logements\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Photos du logement ; la première (ordre le plus petit) est la photo principale. */
class PhotosRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'photos';

    protected static ?string $title = 'Photos';

    protected static ?string $modelLabel = 'photo';

    protected static ?string $pluralModelLabel = 'photos';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('chemin')
                    ->label('Photo')
                    ->image()
                    ->disk('public')
                    ->directory('logements')
                    ->maxSize(4096)
                    ->imageEditor()
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('legende')->label('Légende')->maxLength(255),
                TextInput::make('ordre')->label('Ordre (0 = photo principale)')->integer()->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('legende')
            ->defaultSort('ordre')
            ->reorderable('ordre')
            ->columns([
                ImageColumn::make('chemin')->label('Photo')->disk('public')->imageHeight(64),
                TextColumn::make('legende')->label('Légende')->placeholder('—'),
                TextColumn::make('ordre')->label('Ordre'),
            ])
            ->headerActions([CreateAction::make()->label('Ajouter une photo')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
