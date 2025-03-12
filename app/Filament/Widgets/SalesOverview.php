<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Illuminate\Support\HtmlString;

final class SalesOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected static ?string $pollingInterval = '10s';

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    protected function getCards(): array
    {
        $todayTotal = (float) Order::whereDate('created_at', now()->toDateString())->sum('total_price');
        $yesterdayTotal = (float) Order::whereDate('created_at', now()->subDay()->toDateString())->sum('total_price');

        $percentageChange = 0;
        if ($yesterdayTotal > 0) {
            $percentageChange = (($todayTotal - $yesterdayTotal) / $yesterdayTotal) * 100;
        } elseif ($todayTotal > 0 && $yesterdayTotal === 0.0) {
            $percentageChange = 100;
        }

        $trend = collect(range(6, 0))->map(function ($day) {
            return [
                'date' => now()->subDays((int) $day)->toDateString(),
                'total' => (float) Order::whereDate('created_at', now()->subDays((int) $day)->toDateString())
                    ->sum('total_price'),
            ];
        })->values();

        $cards = [
            Card::make('今日の売上', new HtmlString('¥'.number_format($todayTotal)))
                ->description($yesterdayTotal === 0.0 ? ($todayTotal > 0 ? '+100.0%' : '±0.0%') : ($percentageChange >= 0 ? '+'.number_format($percentageChange, 1).'%' : number_format($percentageChange, 1).'%'))
                ->descriptionIcon($yesterdayTotal === 0.0 ? ($todayTotal > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-minus') : ($percentageChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down'))
                ->color($yesterdayTotal === 0.0 ? ($todayTotal > 0 ? 'success' : 'gray') : ($percentageChange >= 0 ? 'success' : 'danger'))
                ->chart($trend->pluck('total')->toArray())
                ->chartColor($yesterdayTotal === 0.0 ? ($todayTotal > 0 ? 'success' : 'gray') : ($percentageChange >= 0 ? 'success' : 'danger')),

            Card::make('総売上', new HtmlString('¥'.number_format((float) Order::sum('total_price'))))
                ->chart($trend->pluck('total')->toArray())
                ->chartColor('primary'),
        ];

        if ($yesterdayTotal > 0) {
            array_splice($cards, 1, 0, [
                Card::make('昨日の売上', new HtmlString('¥'.number_format($yesterdayTotal))),
            ]);
        }

        return $cards;
    }
}
