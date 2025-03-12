<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Models\LoginAttempt;
use App\Models\User;
use Coderflex\FilamentTurnstile\Forms\Components\Turnstile;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Support\Facades\Auth;

final class Login extends BaseLogin
{
    public ?array $data = [];

    protected static string $view = 'filament.pages.auth.login';

    public string $name = '';

    public string $password = '';

    public bool $remember = false;

    public string $turnstileToken = '';

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        TextInput::make('name')
                            ->label('ユーザー名')
                            ->required()
                            ->placeholder('ユーザー名を入力してください'),
                        TextInput::make('password')
                            ->label('パスワード')
                            ->password()
                            ->required()
                            ->placeholder('パスワードを入力してください'),
                        Checkbox::make('remember')
                            ->label('Remember me'),
                        Turnstile::make('turnstileToken')
                            ->theme('auto')
                            ->visible(config('services.turnstile.enable')),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    public function authenticate(): ?LoginResponse
    {
        $this->validate();

        $credential = [
            'name' => $this->data['name'],
            'password' => $this->data['password'],
        ];

        $ipAddress = request()->ip();
        $loginAttempt = LoginAttempt::where('ip_address', $ipAddress)->first();

        $attemptLimit = (int) config('auth.attempt_limit', 5);
        $blockTime = (int) config('auth.block_time', 60);

        if ($loginAttempt && $loginAttempt->attempts >= $attemptLimit) {
            $lastAttemptTime = $loginAttempt->last_attempt_at;
            if ($lastAttemptTime && now()->diffInMinutes($lastAttemptTime) < $blockTime) {
                activity()
                    ->useLog('warning')
                    ->withProperties(['ip_address' => $ipAddress])
                    ->log("IPアドレス '{$ipAddress}' からのログインが試行制限に達したためブロックされました");
                $this->addError('data.name', 'このIPアドレスからのログインはブロックされています。');

                return null;
            }
            $loginAttempt->attempts = 0;
            $loginAttempt->last_attempt_at = null;
            $loginAttempt->save();
        }

        $user = User::where('name', $this->data['name'])->first();
        if ($user && !$user->is_active) {
            $this->addError('data.name', 'このアカウントは無効です。');

            return null;
        }

        if (Auth::guard(config('filament.auth.guard'))->attempt($credential, $this->data['remember'])) {
            if ($loginAttempt) {
                $loginAttempt->delete();
            }
            activity()
                ->useLog('info')
                ->withProperties(['ip_address' => $ipAddress])
                ->log("ユーザー '{$this->data['name']}' がログインしました");

            return app(LoginResponse::class);
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

        activity()
            ->useLog('error')
            ->withProperties(['ip_address' => $ipAddress])
            ->log("ユーザー '{$this->data['name']}' がログインに失敗しました");
        $this->addError('data.name', 'ログイン情報が正しくありません。');

        return null;
    }
}
