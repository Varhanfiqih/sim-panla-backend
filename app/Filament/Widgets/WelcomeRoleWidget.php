<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class WelcomeRoleWidget extends Widget
{
    protected static string $view = 'filament.widgets.welcome-role-widget';
    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort = -3; // Pastikan widget ini berada di posisi paling atas
}
