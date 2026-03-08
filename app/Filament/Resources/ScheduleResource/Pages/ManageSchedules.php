<?php

namespace App\Filament\Resources\ScheduleResource\Pages;

use App\Filament\Resources\ScheduleResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageSchedules extends ManageRecords
{
    protected static string $resource = ScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('grid')
                ->label('Lihat Jadwal Grid')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->url(ScheduleResource::getUrl('calendar')),
            Actions\CreateAction::make(),
        ];
    }
}
