<?php

namespace App\Filament\Resources\Users;

use App\Filament\Concerns\AccesParRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Comptes de tous les rôles. Réservé à l'administrateur. */
class UserResource extends Resource
{
    use AccesParRole;

    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'utilisateur';

    protected static ?string $pluralModelLabel = 'Utilisateurs';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    /** On ne supprime pas son propre compte. */
    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return $record->getKey() === auth()->id()
            ? Response::deny('Vous ne pouvez pas supprimer votre propre compte.')
            : static::getAuthorizationResponse('delete', $record);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
