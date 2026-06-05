<?php

namespace App\Http\Responses;

use App\Filament\Pages\AdminItDashboard;
use App\Filament\Pages\PrincipalDashboard;
use App\Filament\Pages\SuperAdminDashboard;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class RoleBasedLoginResponse implements LoginResponse
{
    public function toResponse($request): RedirectResponse | Redirector
    {
        $user = Filament::auth()->user();

        $url = match (true) {
            $user?->isSuperAdmin() => SuperAdminDashboard::getUrl(isAbsolute: false),
            $user?->isAdminIT() => AdminItDashboard::getUrl(isAbsolute: false),
            $user?->isKepsek() => PrincipalDashboard::getUrl(isAbsolute: false),
            default => Filament::getUrl(),
        };

        return redirect()->to($url);
    }
}
