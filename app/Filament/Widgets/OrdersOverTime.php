<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Orders;
use Coolsam\FilamentFlatpickr\Forms\Components\Flatpickr;
use Filament\Forms\Components\Grid;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

final class OrdersOverTime extends ApexChartWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = '時間別注文数';

    protected static ?int $contentHeight = 300;

    protected static ?string $pollingInterval = '10s';

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    protected function getFilters(): ?array
    {
        return [
            'today' => '今日',
            'week' => '週間',
            'month' => '月間',
            'custom' => 'カスタム',
        ];
    }

    protected function getFormSchema(): array
    {
        return [
            Grid::make(2)->schema([
                Flatpickr::make('startDate')
                    ->label('開始日')
                    ->default(now()->subDays(7))
                    ->reactive(),

                Flatpickr::make('endDate')
                    ->label('終了日')
                    ->default(now())
                    ->reactive(),
            ]),
        ];
    }

    protected function getOptions(): array
    {
        $driver = config('database.default');
        $isMySQL = $driver === 'mysql';

        $filter = $this->filter ?? 'today';
        /** @phpstan-ignore-next-line */
        $formData = $this->form->getState();

        $startDate = match ($filter) {
            'today' => now()->startOfDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'custom' => $formData['startDate'] ?? now()->subDays(7),
            default => now()->subDay(),
        };

        $endDate = match ($filter) {
            'custom' => $formData['endDate'] ?? now(),
            default => now(),
        };

        $dateFormat = $isMySQL
            ? "DATE_FORMAT(created_at, '%m/%d %H:%i')"
            : "strftime('%m/%d %H:%M', created_at)";

        $orders = Orders::query()
            ->selectRaw("{$dateFormat} as hour, COUNT(*) as count")
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupByRaw($dateFormat)
            ->orderBy('hour')
            ->pluck('count', 'hour');

        return [
            'chart' => [
                'type' => 'line',
                'height' => 300,
            ],
            'series' => [
                [
                    'name' => '注文数',
                    'data' => $orders->values()->toArray(),
                ],
            ],
            'xaxis' => [
                'categories' => $orders->keys()->toArray(),
                'title' => ['text' => '時間'],
            ],
            'yaxis' => [
                'title' => ['text' => '注文数'],
            ],
            'colors' => ['#FF4560'],
        ];
    }
}
