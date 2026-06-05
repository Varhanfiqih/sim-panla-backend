<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    public function mount(): void
    {
        $user = auth()->user();

        $url = match (true) {
            $user?->isSuperAdmin() => SuperAdminDashboard::getUrl(isAbsolute: false),
            $user?->isAdminIT() => AdminItDashboard::getUrl(isAbsolute: false),
            $user?->isKepsek() => PrincipalDashboard::getUrl(isAbsolute: false),
            default => route('filament.admin.auth.login'),
        };

        $this->redirect($url, navigate: true);
    }

    public function getTitle(): string | Htmlable
    {
        $role = auth()->user()?->role ?? '';
        return 'Dashboard ' . $role;
    }
}
