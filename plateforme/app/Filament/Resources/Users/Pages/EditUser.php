<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /** @var list<string> rôles avant l'enregistrement, pour le journal d'activité */
    private array $rolesAvant = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        $this->rolesAvant = $this->record->roles()->pluck('name')->sort()->values()->all();
    }

    /** Les rôles passent par une table de liaison : le changement est noté à part dans le journal. */
    protected function afterSave(): void
    {
        $apres = $this->record->roles()->pluck('name')->sort()->values()->all();
        if ($apres !== $this->rolesAvant) {
            activity('administration')->performedOn($this->record)->event('roles')
                ->withProperties(['avant' => $this->rolesAvant, 'apres' => $apres])
                ->log('Rôles de '.$this->record->name.' modifiés');
        }
    }
}
