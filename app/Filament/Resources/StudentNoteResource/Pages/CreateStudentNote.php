<?php

namespace App\Filament\Resources\StudentNoteResource\Pages;

use App\Filament\Resources\StudentNoteResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateStudentNote extends CreateRecord
{
    protected static string $resource = StudentNoteResource::class;
}
