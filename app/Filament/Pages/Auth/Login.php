<?php

namespace App\Filament\Pages\Auth;

use Illuminate\Support\Facades\Auth;
use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Facades\Filament;

class Login extends BaseLogin
{
    protected static string $view = 'filament.pages.auth.login';

    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    public function authenticate(): ?\Filament\Http\Responses\Auth\Contracts\LoginResponse
    {        
        if (Auth::guard(config('filament.auth.guard'))->attempt([
            'email'    => $this->email,
            'password' => $this->password,
        ], $this->remember)) {            
            $url = session()->pull('url.intended', Filament::getUrl());
            $this->redirect($url);
            return null;
        }

        $this->addError('email', 'ログイン情報が正しくありません。');
        return null;
    }
}
