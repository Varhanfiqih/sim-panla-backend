<?php

namespace App\Filament\Resources\ScheduleResource\Pages;

use App\Filament\Resources\ScheduleResource;
use Filament\Resources\Pages\Page;

class ScheduleCalendar extends Page
{
    protected static string $resource = ScheduleResource::class;

    protected static string $view = 'filament.resources.schedule-resource.pages.schedule-calendar';
}
