<?php

namespace App\Filament\Resources\GradePeriodResource\Pages;

use App\Filament\Resources\GradePeriodResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGradePeriod extends EditRecord
{
    protected static string $resource = GradePeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
