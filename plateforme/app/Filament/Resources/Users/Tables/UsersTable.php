<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\Role;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\Colonnes;
use App\Models\User;
use App\Support\Telephone;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        $roles = collect(Role::cases())->mapWithKeys(fn (Role $r) => [$r->value => $r->libelle()])->all();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('supprime_le')->with('roles'))   // sans les comptes supprimés par leur titulaire
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Nom')->weight(FontWeight::SemiBold)->searchable()->sortable()
                    ->description(fn (User $record) => Colonnes::contact($record)),
                TextColumn::make('telephone')->label('Téléphone')->searchable()->placeholder('—')
                    ->formatStateUsing(fn (?string $state) => $state ? Telephone::lisible($state) : null)->visibleFrom('2xl'),
                TextColumn::make('email')->label('Email')->searchable()->placeholder('—')->toggleable()->visibleFrom('2xl'),
                TextColumn::make('roles.name')->label('Rôles')->badge()
                    ->formatStateUsing(fn (string $state) => $roles[$state] ?? $state)
                    ->color(fn (string $state) => in_array($state, Role::equipeVivi(), true) ? 'primary' : 'gray'),
                ToggleColumn::make('actif')->label('Actif')
                    ->disabled(fn (User $record) => $record->getKey() === auth()->id() || ! UserResource::canCreate()),
                TextColumn::make('created_at')->label('Inscrit le')->date('d/m/Y')->sortable()->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Rôle')
                    ->options($roles)
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($q, $role) => $q->role($role))),
            ])
            ->recordActions([EditAction::make()]);
    }
}
