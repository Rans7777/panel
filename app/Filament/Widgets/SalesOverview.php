<?php

namespace App\Filament\Widgets;

use App\Models\Orders;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Illuminate\Support\HtmlString;

class SalesOverview extends BaseWidget
{
    protected static ?string $pollingInterval = '10s';

    protected function getCards(): array
    {
        $todayTotal = Orders::whereDate('created_at', now()->toDateString())->sum('total_price');
        $yesterdayTotal = Orders::whereDate('created_at', now()->subDay()->toDateString())->sum('total_price');

        $percentageChange = $yesterdayTotal ? (($todayTotal - $yesterdayTotal) / $yesterdayTotal) * 100 : 0;
        $trend = collect(range(6, 0))->map(function ($day) {
            return [
                'date' => now()->subDays($day)->toDateString(),
                'total' => Orders::whereDate('created_at', now()->subDays($day)->toDateString())
                    ->sum('total_price')
            ];
        })->values();

        $cards = [
            Card::make('今日の売上', new HtmlString('¥' . number_format($todayTotal)))
                ->description($percentageChange >= 0 ? '+' . number_format($percentageChange, 1) . '%' : number_format($percentageChange, 1) . '%')
                ->descriptionIcon($percentageChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($percentageChange >= 0 ? 'success' : 'danger')
                ->chart($trend->pluck('total')->toArray())
                ->chartColor($percentageChange >= 0 ? 'success' : 'danger'),
            
            Card::make('総売上', new HtmlString('¥' . number_format(Orders::sum('total_price'))))
        ];

        if ($yesterdayTotal > 0) {
            array_splice($cards, 1, 0, [
                Card::make('昨日の売上', new HtmlString('¥' . number_format($yesterdayTotal)))
            ]);
        }

        return $cards;
    }
}
