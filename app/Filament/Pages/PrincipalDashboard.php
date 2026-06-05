<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class PrincipalDashboard extends BaseDashboard
{
    protected static string $routePath = 'kepala-sekolah';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->isKepsek() ?? false;
    }

    public function getTitle(): string | Htmlable
    {
        return 'Dashboard Kepala Sekolah';
    }
}
