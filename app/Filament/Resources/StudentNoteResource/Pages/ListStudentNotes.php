<?php

namespace App\Filament\Resources\StudentNoteResource\Pages;

use App\Filament\Resources\StudentNoteResource;
use Filament\Resources\Pages\ListRecords;

class ListStudentNotes extends ListRecords
{
    protected static string $resource = StudentNoteResource::class;
}
