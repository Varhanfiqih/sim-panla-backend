<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class AdminItDashboard extends BaseDashboard
{
    protected static string $routePath = 'admin-it';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdminIT() ?? false;
    }

    public function getTitle(): string | Htmlable
    {
        return 'Dashboard Admin IT';
    }
}
