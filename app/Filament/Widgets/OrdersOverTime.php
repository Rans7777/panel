<?php

namespace App\Filament\Widgets;

use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;
use App\Models\Orders;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;

class OrdersOverTime extends ApexChartWidget
{
    protected static ?int $sort = 2;
    protected static ?string $heading = '時間別注文数';
    protected static ?int $contentHeight = 300;
    protected static ?string $pollingInterval = '10s';

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
                DatePicker::make('startDate')
                    ->label('開始日')
                    ->default(now()->subDays(7))
                    ->reactive(),

                DatePicker::make('endDate')
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

        // フィルターに基づいて開始日を決定
        $startDate = match ($filter) {
            'today' => now()->startOfDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'custom' => $this->form['startDate'] ?? now()->subDays(7),
            default => now()->subDay(),
        };

        $endDate = match ($filter) {
            'custom' => $this->form['endDate'] ?? now(),
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
