<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Exception;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use LaraZeus\Quantity\Components\Quantity;
use Log;
use Parfaitementweb\FilamentCountryField\Forms\Components\Country;
use Rawilk\FilamentPasswordInput\Password;
use Tapp\FilamentTimezoneField\Forms\Components\TimezoneSelect;
use Wallo\FilamentSelectify\Components\ToggleButton;

final class Settings extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog';

    protected static string $view = 'filament.pages.settings';

    protected static ?string $title = '環境設定';

    protected static ?string $navigationGroup = 'パネル管理';

    protected static ?int $navigationSort = 2;

    protected ?array $data = [];

    public $APP_NAME;

    public $APP_DEBUG;

    public $APP_TIMEZONE;

    public $APP_LOCALE;

    public $APP_URL;

    public $LOG_LEVEL;

    public $DB_CONNECTION;

    public $DB_HOST;

    public $DB_PORT;

    public $DB_DATABASE;

    public $DB_USERNAME;

    public $DB_PASSWORD;

    public $TURNSTILE_SITEKEY;

    public $TURNSTILE_SECRET;

    public $LOGIN_ATTEMPT_LIMIT;

    public $LOGIN_BLOCK_TIME;

    public $DISCORD_WEBHOOK_URL;

    public function mount(): void
    {
        if (!auth()->user()->hasRole('admin')) {
            Notification::make()
                ->warning()
                ->title('アクセス拒否')
                ->body('管理者権限が必要です。')
                ->send();
            throw new HttpResponseException(new RedirectResponse('/admin/'));
        }

        /** @phpstan-ignore-next-line */
        $this->form->fill([
            'APP_NAME' => config('app.name', ''),
            'APP_DEBUG' => config('app.debug', ''),
            'APP_TIMEZONE' => config('app.timezone', ''),
            'APP_LOCALE' => config('app.locale', ''),
            'APP_URL' => config('app.url', ''),
            'LOG_LEVEL' => config('logging.level', ''),
            'DB_CONNECTION' => config('database.default', ''),
            'DB_HOST' => config('database.connections.mysql.host', ''),
            'DB_PORT' => config('database.connections.mysql.port', ''),
            'DB_DATABASE' => config('database.connections.mysql.database', ''),
            'DB_USERNAME' => config('database.connections.mysql.username', ''),
            'DB_PASSWORD' => config('database.connections.mysql.password', ''),
            'TURNSTILE_SITEKEY' => config('services.turnstile.sitekey', ''),
            'TURNSTILE_SECRET' => config('services.turnstile.secret', ''),
            'LOGIN_ATTEMPT_LIMIT' => config('auth.attempt_limit'),
            'LOGIN_BLOCK_TIME' => config('auth.block_time'),
            'DISCORD_WEBHOOK_URL' => config('discord-alerts.webhook_urls.default', ''),
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Section::make('アプリケーション基本設定')
                ->description('アプリケーションの基本情報を設定します。')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('APP_NAME')
                        ->label('APP_NAME')
                        ->required(),
                    ToggleButton::make('APP_DEBUG')
                        ->label('APP_DEBUG')
                        ->required()
                        ->offLabel('無効')
                        ->onLabel('有効'),
                    TimezoneSelect::make('APP_TIMEZONE')
                        ->label('APP_TIMEZONE')
                        ->required(),
                    Country::make('APP_LOCALE')
                        ->label('APP_LOCALE')
                        ->required(),
                    Forms\Components\TextInput::make('APP_URL')
                        ->label('APP_URL')
                        ->url()
                        ->required(),
                ]),

            Forms\Components\Section::make('ログ・認証設定')
                ->description('ログレベルやログイン試行回数、ブロック時間の設定を行います。')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\Select::make('LOG_LEVEL')
                        ->label('LOG_LEVEL')
                        ->options([
                            'debug' => 'debug',
                            'info' => 'info',
                            'notice' => 'notice',
                            'warning' => 'warning',
                            'error' => 'error',
                            'critical' => 'critical',
                        ])
                        ->required(),
                    Quantity::make('LOGIN_ATTEMPT_LIMIT')
                        ->label('LOGIN_ATTEMPT_LIMIT')
                        ->default(5)
                        ->minValue(0)
                        ->suffix('回')
                        ->required(),
                    Quantity::make('LOGIN_BLOCK_TIME')
                        ->label('LOGIN_BLOCK_TIME')
                        ->numeric()
                        ->suffix('分')
                        ->required(),
                ]),

            Forms\Components\Section::make('データベース接続設定')
                ->description('利用するデータベースの種類と接続情報を設定します。')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\Select::make('DB_CONNECTION')
                        ->label('DB_CONNECTION')
                        ->options([
                            'sqlite' => 'SQLite',
                            'mysql' => 'MySQL / MariaDB',
                        ])
                        ->required()
                        ->reactive(),
                    Forms\Components\TextInput::make('DB_HOST')
                        ->label('DB_HOST')
                        ->required()
                        ->hidden(fn (callable $get): bool => $get('DB_CONNECTION') === 'sqlite'),
                    Forms\Components\TextInput::make('DB_PORT')
                        ->label('DB_PORT')
                        ->required()
                        ->hidden(fn (callable $get): bool => $get('DB_CONNECTION') === 'sqlite'),
                    Forms\Components\TextInput::make('DB_DATABASE')
                        ->label('DB_DATABASE')
                        ->required()
                        ->hidden(fn (callable $get): bool => $get('DB_CONNECTION') === 'sqlite'),
                    Forms\Components\TextInput::make('DB_USERNAME')
                        ->label('DB_USERNAME')
                        ->required()
                        ->hidden(fn (callable $get): bool => $get('DB_CONNECTION') === 'sqlite'),
                    Password::make('DB_PASSWORD')
                        ->label('DB_PASSWORD')
                        ->required()
                        ->hidden(fn (callable $get): bool => $get('DB_CONNECTION') === 'sqlite'),
                ]),

            Forms\Components\Section::make('Turnstile サービス設定')
                ->description('Turnstile サービスのサイトキーとシークレットキーを設定します。')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Password::make('TURNSTILE_SITEKEY')
                        ->label('TURNSTILE_SITEKEY'),
                    Password::make('TURNSTILE_SECRET')
                        ->label('TURNSTILE_SECRET'),
                ]),

            Forms\Components\Section::make('webhook 通知設定')
                ->description('通知用のWebhook URL を設定します。')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('DISCORD_WEBHOOK_URL')
                        ->label('DISCORD_WEBHOOK_URL')
                        ->url(),
                ]),
        ];
    }

    public function updateEnv(): void
    {
        /** @phpstan-ignore-next-line */
        $data = $this->form->getState();
        $envFilePath = base_path('.env');

        if (!File::exists($envFilePath)) {
            Notification::make()
                ->title('.env ファイルが存在しません')
                ->danger()
                ->send();

            return;
        }

        $envContent = File::get($envFilePath);
        $envLines = explode("\n", $envContent);
        $keys = array_keys($data);

        foreach ($keys as $key) {
            $value = $data[$key];

            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } else {
                $value = (string) $value;
            }

            if (mb_strpos($value, ' ') !== false) {
                $value = '"'.$value.'"';
            }
            $found = false;
            foreach ($envLines as $index => $line) {
                if (mb_strpos(trim($line), '#') === 0 || trim($line) === '') {
                    continue;
                }

                if (preg_match('/^'.$key.'\s*=/', $line)) {
                    $envLines[$index] = $key.'='.$value;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $envLines[] = $key.'='.$value;
            }
        }

        $newEnvContent = implode("\n", $envLines);

        try {
            File::put($envFilePath, $newEnvContent);
        } catch (Exception $e) {
            Log::error('Failed to update .env file: '.$e->getMessage());
            Notification::make()
                ->title('.env ファイルの更新に失敗しました')
                ->danger()
                ->send();

            return;
        }

        Artisan::call('config:clear');
        Notification::make()
            ->title('.env が更新されました')
            ->success()
            ->send();

        activity()
            ->useLog('info')
            ->withProperties(['ip_address' => request()->ip()])
            ->log('環境設定が更新されました');
    }

    protected function getActions(): array
    {
        return [
            \Filament\Pages\Actions\Action::make('save')
                ->label('保存')
                ->action('updateEnv'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }
}
