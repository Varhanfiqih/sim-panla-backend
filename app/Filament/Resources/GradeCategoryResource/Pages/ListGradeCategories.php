<?php

namespace App\Filament\Resources\GradeCategoryResource\Pages;

use App\Filament\Resources\GradeCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGradeCategories extends ListRecords
{
    protected static string $resource = GradeCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
