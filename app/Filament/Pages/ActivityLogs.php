<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Forms\Components\DatePicker;
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

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $title = 'アクティビティログ';

    protected static ?string $navigationLabel = 'アクティビティログ';

    protected static string | \UnitEnum | null $navigationGroup = 'パネル管理';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.activity-logs';

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

    protected function isTableReorderable(): bool
    {
        return false;
    }

    protected function getTableQuery()
    {
        return Activity::query()->orderBy('created_at', 'desc');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('log_name')
                ->label('ログレベル')
                ->color(fn ($state) => match ($state) {
                    'info' => 'success',
                    'warning' => 'warning',
                    'error' => 'danger',
                    default => 'gray',
                }),
            Tables\Columns\TextColumn::make('description')
                ->label('ログ')
                ->searchable(),
            Tables\Columns\TextColumn::make('ip_address')
                ->label('IPアドレス')
                ->getStateUsing(fn ($record) => $record->properties['ip_address'] ?? 'N/A')
                ->searchable([
                    'properties->ip_address',
                ]),
            Tables\Columns\TextColumn::make('created_at')
                ->label('日時')
                ->sortable()
                ->dateTime('Y年m月d日 H:i:s'),
        ];
    }

    protected function getTableFilters(): array
    {
        return [
            Tables\Filters\SelectFilter::make('log_name')
                ->label('ログレベル')
                ->options([
                    'info' => 'Info',
                    'warning' => 'Warning',
                    'error' => 'Error',
                ]),
            Tables\Filters\Filter::make('created_at')
                ->label('日時')
                ->schema([
                    DatePicker::make('created_from')
                        ->label('開始日時'),
                    DatePicker::make('created_until')
                        ->label('終了日時'),
                ])
                ->query(function ($query, array $data) {
                    return $query
                        ->when($data['created_from'], fn ($query, $date) => $query->whereDate('created_at', '>=', $date)
                        )
                        ->when($data['created_until'], fn ($query, $date) => $query->whereDate('created_at', '<=', $date)
                        );
                }),
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
