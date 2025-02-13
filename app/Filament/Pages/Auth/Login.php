<?php

namespace App\Filament\Pages\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Facades\Filament;
use App\Models\User;
use App\Models\LoginAttempt;

class Login extends BaseLogin
{
    protected static string $view = 'filament.pages.auth.login';

    public string $name = '';
    public string $password = '';
    public bool $remember = false;
    public string $turnstileToken = '';

    public function authenticate(): ?\Filament\Http\Responses\Auth\Contracts\LoginResponse
    {
        $ipAddress = request()->ip();

        $loginAttempt = LoginAttempt::where('ip_address', $ipAddress)->first();

        $attemptLimit = (int)config('LOGIN_ATTEMPT_LIMIT', 5);
        $blockTime = (int)config('LOGIN_BLOCK_TIME', 60);

        if ($loginAttempt && $loginAttempt->attempts >= $attemptLimit) {
            $lastAttemptTime = $loginAttempt->last_attempt_at;
            if ($lastAttemptTime && now()->diffInMinutes($lastAttemptTime) < $blockTime) {
                $this->addError('name', 'このIPアドレスからのログインはブロックされています。');
                return null;
            } else {
                $loginAttempt->attempts = 0;
                $loginAttempt->last_attempt_at = null;
                $loginAttempt->save();
            }
        }

        if (config('services.turnstile.secret') && config('services.turnstile.sitekey')) {
            $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
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

        $user = User::where('name', $this->name)->first();
        if ($user && !$user->is_active) {
            $this->addError('name', 'このアカウントは無効です。');
            return null;
        }

        if (Auth::guard(config('filament.auth.guard'))->attempt([
            'name'     => $this->name,
            'password' => $this->password,
        ], $this->remember)) {
            if ($loginAttempt) {
                $loginAttempt->delete();
            }
            $url = session()->pull('url.intended', Filament::getUrl());
            $this->redirect($url);
            return null;
        }

        if ($loginAttempt) {
            $loginAttempt->increment('attempts');
            $loginAttempt->last_attempt_at = now();
            $loginAttempt->save();
        } else {
            LoginAttempt::create([
                'ip_address' => $ipAddress,
                'attempts' => 1,
                'last_attempt_at' => now(),
            ]);
        }

        $this->addError('name', 'ログイン情報が正しくありません。');
        return null;
    }
}
