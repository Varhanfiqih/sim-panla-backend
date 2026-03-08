<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Component;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getNipFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ])
            ->statePath('data');
    }

    protected function getNipFormComponent(): Component
    {
        return TextInput::make('nip')
            ->label('NIP / NISN')
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            'nip' => $data['nip'],
            'password' => $data['password'],
        ];
    }
    
    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.nip' => __('filament-panels::pages/auth/login.messages.failed'),
        ]);
    }
}
