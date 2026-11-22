<?php

namespace App\Filament\Resources\Roles;

use App\Enums\Role;
use App\Filament\Concerns\AccesParRole;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as RoleSpatie;
use UnitEnum;

/**
 * Rôles et permissions de l'administration. L'administrateur coche, pour chaque rôle de l'équipe
 * (caissière, ou rôle créé ici : comptable, superviseur…), les écrans qu'il peut voir ou gérer.
 */
class RoleResource extends Resource
{
    use AccesParRole;

    protected static ?string $model = RoleSpatie::class;

    protected static ?string $slug = 'roles';

    protected static ?string $modelLabel = 'rôle';

    protected static ?string $pluralModelLabel = 'Rôles et permissions';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationLabel = 'Rôles et permissions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 3;

    /** Nom affiché : libellé du rôle prévu, sinon le nom saisi. */
    public static function libelle(RoleSpatie $role): string
    {
        return Role::tryFrom($role->name)?->libelle() ?? Str::of($role->name)->replace(['-', '_'], ' ')->ucfirst();
    }

    public static function estPrevu(?RoleSpatie $role): bool
    {
        return $role !== null && Role::tryFrom($role->name) !== null;
    }

    public static function estDuSite(?RoleSpatie $role): bool
    {
        return $role !== null && in_array(Role::tryFrom($role->name), Permissions::ROLES_DU_SITE, true);
    }

    public static function form(Schema $schema): Schema
    {
        $groupes = collect(Permissions::catalogue())->groupBy(fn ($p) => $p[1], preserveKeys: true);

        $sections = $groupes->map(fn ($permissions, $groupe) => Section::make($groupe)
            ->compact()
            ->collapsible()
            ->schema([
                CheckboxList::make('permissions_'.Str::slug($groupe, '_'))
                    ->hiddenLabel()
                    ->options($permissions->map(fn ($p) => $p[0])->all())
                    ->columns(2)
                    ->bulkToggleable()
                    ->afterStateHydrated(fn ($component, ?RoleSpatie $record) => $component->state(
                        $record ? $record->permissions->pluck('name')->intersect($permissions->keys())->values()->all() : []))
                    ->dehydrated(false)
                    ->saveRelationshipsUsing(function (RoleSpatie $record, $state) use ($permissions) {
                        // Seules les permissions de ce groupe sont remplacées ; le journal garde la trace des changements
                        $avant = $record->permissions->pluck('name')->intersect($permissions->keys())->values();
                        $apres = collect((array) $state)->intersect($permissions->keys())->values();
                        $retirees = $avant->diff($apres);
                        $ajoutees = $apres->diff($avant);
                        if ($retirees->isNotEmpty()) {
                            $record->revokePermissionTo($retirees->all());
                        }
                        if ($ajoutees->isNotEmpty()) {
                            $record->givePermissionTo($ajoutees->all());
                        }
                        if ($retirees->isNotEmpty() || $ajoutees->isNotEmpty()) {
                            activity('administration')->performedOn($record)->event('permissions')
                                ->withProperties(['ajoutees' => $ajoutees->map(fn ($p) => Permissions::libelle($p))->all(), 'retirees' => $retirees->map(fn ($p) => Permissions::libelle($p))->all()])
                                ->log('Permissions du rôle « '.static::libelle($record).' » modifiées');
                        }
                        $record->load('permissions');
                    }),
            ]))->values()->all();

        return $schema
            ->columns(1)
            ->components([
                Section::make('Rôle')
                    ->schema([
                        TextInput::make('name')->label('Nom du rôle')
                            ->required()->maxLength(60)->unique(ignoreRecord: true)
                            ->placeholder('Ex. comptable, superviseur')
                            ->helperText('Les utilisateurs reçoivent ce rôle dans Administration › Utilisateurs.')
                            ->disabled(fn (?RoleSpatie $record) => static::estPrevu($record))
                            ->dehydrateStateUsing(fn ($state) => Str::of($state)->trim()->lower()->replace(' ', '-')->toString()),
                        TextInput::make('guard_name')->default('web')->hidden()->dehydratedWhenHidden(),
                    ]),
                Section::make('Accès complet')
                    ->visible(fn (?RoleSpatie $record) => $record?->name === Role::Administrateur->value)
                    ->schema([Text::make('L’administrateur a toujours toutes les permissions : elles ne se modifient pas, pour qu’il ne puisse pas se retirer l’accès.')]),
                Section::make('Rôle du site')
                    ->visible(fn (?RoleSpatie $record) => static::estDuSite($record))
                    ->schema([Text::make('Ce rôle sert sur le site (client, propriétaire, gestionnaire) : il ne donne pas accès à l’administration.')]),
                Section::make('Permissions')
                    ->description('« Voir » : consulter la liste et les fiches. « Gérer » : créer, modifier, valider, supprimer. « Se connecter à l’administration » est indispensable pour entrer.')
                    ->visible(fn (?RoleSpatie $record) => $record?->name !== Role::Administrateur->value && ! static::estDuSite($record))
                    ->schema($sections),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['users', 'permissions']))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('name')->label('Rôle')
                    ->formatStateUsing(fn ($state, RoleSpatie $record) => static::libelle($record))
                    ->description(fn (RoleSpatie $record) => match (true) {
                        $record->name === Role::Administrateur->value => 'Toutes les permissions',
                        static::estDuSite($record) => 'Rôle du site, sans accès à l’administration',
                        static::estPrevu($record) => 'Rôle prévu de l’équipe VIVI',
                        default => 'Rôle créé par l’administrateur',
                    })
                    ->weight('semibold')->searchable(),
                TextColumn::make('users_count')->label('Utilisateurs')->alignCenter(),
                TextColumn::make('permissions_count')->label('Permissions')->alignCenter()
                    ->formatStateUsing(fn ($state, RoleSpatie $record) => $record->name === Role::Administrateur->value ? 'Toutes' : (static::estDuSite($record) ? '—' : $state)),
            ])
            ->recordActions([
                EditAction::make()->label('Permissions')->icon(Heroicon::OutlinedAdjustmentsHorizontal),
                DeleteAction::make()
                    ->visible(fn (RoleSpatie $record) => ! static::estPrevu($record) && $record->users_count === 0)
                    ->after(fn (RoleSpatie $record) => activity('administration')->event('suppression')->withProperties(['role' => $record->name])->log('Rôle « '.static::libelle($record).' » supprimé')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/nouveau'),
            'edit' => EditRole::route('/{record}/modifier'),
        ];
    }
}
