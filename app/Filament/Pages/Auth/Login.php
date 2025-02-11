<?php

namespace App\Filament\Pages\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Facades\Filament;

class Login extends BaseLogin
{
    protected static string $view = 'filament.pages.auth.login';

    public string $name = '';
    public string $password = '';
    public bool $remember = false;
    public string $turnstileToken = '';

    public function authenticate(): ?\Filament\Http\Responses\Auth\Contracts\LoginResponse
    {
        if (config('services.turnstile.secret') && config('services.turnstile.sitekey')) {
            $response = Http::withOptions(['verify' => false])->asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret'   => config('services.turnstile.secret'),
                'response' => $this->turnstileToken,
                'remoteip' => request()->ip(),
            ]);
            
            $result = $response->json();
            if (!isset($result['success']) || !$result['success']) {
                $this->addError('turnstileToken', 'Cloudflare Turnstile 認証に失敗しました。');
                return null;
            }
        }

        if (Auth::guard(config('filament.auth.guard'))->attempt([
            'name'     => $this->name,
            'password' => $this->password,
        ], $this->remember)) {
            $url = session()->pull('url.intended', Filament::getUrl());
            $this->redirect($url);
            return null;
        }

        $this->addError('name', 'ログイン情報が正しくありません。');
        return null;
    }
}
