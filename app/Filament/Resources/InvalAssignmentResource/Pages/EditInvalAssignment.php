<?php

namespace App\Filament\Resources\InvalAssignmentResource\Pages;

use App\Filament\Resources\InvalAssignmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvalAssignment extends EditRecord
{
    protected static string $resource = InvalAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
