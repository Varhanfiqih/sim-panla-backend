<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    public function getTitle(): string | Htmlable
    {
        $role = auth()->user()?->role ?? '';
        return 'Dashboard ' . $role;
    }
}
