<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Spatie\Activitylog\Models\Activity;

final class ActivityLogs extends Page implements HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $title = 'アクティビティログ';

    protected static ?string $navigationLabel = 'アクティビティログ';

    protected static ?string $navigationGroup = '管理';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.activity-logs';

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
    }

    protected function getTableQuery()
    {
        return Activity::query()->orderBy('created_at', 'desc');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\BadgeColumn::make('log_name')
                ->label('ログレベル')
                ->colors([
                    'info' => 'primary',
                    'warning' => 'warning',
                    'error' => 'danger',
                ]),
            Tables\Columns\TextColumn::make('description')
                ->label('ログ'),
            Tables\Columns\TextColumn::make('ip_address')
                ->label('IPアドレス')
                ->getStateUsing(fn ($record) => $record->properties['ip_address'] ?? 'N/A'),
            Tables\Columns\TextColumn::make('created_at')
                ->dateTime()
                ->label('日時'),
        ];
    }

    protected function getViewData(): array
    {
        return array_merge(parent::getViewData(), [
            'table' => $this->table,
        ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }
}
