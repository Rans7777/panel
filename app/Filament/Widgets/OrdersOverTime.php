<?php

namespace App\Filament\Widgets;

use App\Models\Orders;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;
use Illuminate\Support\Carbon;

class OrdersOverTime extends ApexChartWidget
{
    protected static ?string $chartId = 'ordersOverTime';
    protected static ?string $heading = '時間別注文数';
    protected static ?int $contentHeight = 300;
    protected static ?string $pollingInterval = '10s';

    protected function getOptions(): array
    {
        $data = $this->getData();

        return [
            'chart' => [
                'type' => 'area',
                'height' => 300,
            ],
            'series' => [
                [
                    'name' => '注文数',
                    'data' => $data['counts'],
                ]
            ],
            'xaxis' => [
                'categories' => $data['hours'],
                'labels' => [
                    'style' => [
                        'fontFamily' => 'inherit',
                    ],
                ],
            ],
            'stroke' => [
                'curve' => 'smooth',
            ],
        ];
    }

    protected function getData(): array
    {
        $orders = Orders::query()
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m-%d %H:00:00") as hour, COUNT(*) as count')
            ->where('created_at', '>=', now()->subHours(48))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        return [
            'hours' => $orders->pluck('hour')->map(fn ($hour) => 
                Carbon::parse($hour)->format('m/d H:i')
            )->toArray(),
            'counts' => $orders->pluck('count')->toArray(),
        ];
    }
}
