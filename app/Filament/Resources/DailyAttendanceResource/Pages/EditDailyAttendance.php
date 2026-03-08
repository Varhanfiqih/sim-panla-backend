<?php

namespace App\Filament\Resources\DailyAttendanceResource\Pages;

use App\Filament\Resources\DailyAttendanceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDailyAttendance extends EditRecord
{
    protected static string $resource = DailyAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
