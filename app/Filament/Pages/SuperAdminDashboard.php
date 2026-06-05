<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class SuperAdminDashboard extends BaseDashboard
{
    protected static string $routePath = 'super-admin';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function getTitle(): string | Htmlable
    {
        return 'Dashboard Super Admin';
    }
}
