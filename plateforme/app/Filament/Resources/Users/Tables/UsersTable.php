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
                // Rémunération personnalisée d'un propriétaire (sinon règle générale des Paramètres)
                // Vide pour qui n'est pas propriétaire ; un taux personnalisé est affiché même sans modèle imposé
                TextColumn::make('modele_economique')->label('Rémunération')->badge()->placeholder('—')
                    ->state(function (User $record): ?string {
                        if (! $record->hasRole(Role::Proprietaire->value)) {
                            return null;
                        }
                        $taux = $record->commission_retrait_taux !== null ? ' '.\App\Services\Caisse::taux($record->commission_retrait_taux) : '';

                        return match ($record->modele_economique) {
                            \App\Services\ModeleEconomique::COMMISSION => 'Commission'.$taux,
                            \App\Services\ModeleEconomique::ABONNEMENT => 'Abonnement',
                            default => 'Règle générale'.($taux !== '' ? ' · taux'.$taux : ''),
                        };
                    })
                    ->color(fn (User $record) => match ($record->modele_economique) {
                        \App\Services\ModeleEconomique::COMMISSION => 'warning',
                        \App\Services\ModeleEconomique::ABONNEMENT => 'info',
                        default => 'gray',
                    })
                    ->toggleable()->visibleFrom('lg'),
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
