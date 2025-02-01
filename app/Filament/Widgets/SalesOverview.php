<?php

namespace App\Filament\Widgets;

use App\Models\Orders;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Illuminate\Support\HtmlString;

class SalesOverview extends BaseWidget
{
    protected function getCards(): array
    {
        $todayTotal = Orders::whereDate('created_at', now()->toDateString())->sum('total_price');
        $yesterdayTotal = Orders::whereDate('created_at', now()->subDay()->toDateString())->sum('total_price');

        if ($todayTotal > $yesterdayTotal) {
            $arrow = '<span style="color: #10B981;">↗</span>';
        } elseif ($todayTotal < $yesterdayTotal) {
            $arrow = '<span style="color: #EF4444;">↘</span>';
        } else {
            $arrow = '<span style="color: #808080;">→</span>';
        }

        return [
            Card::make(
                '今日の売上',
                new HtmlString('¥' . number_format($todayTotal) . '&ensp;' . $arrow)
            ),
            Card::make(
                '総売上', 
                new HtmlString('¥' . number_format(Orders::sum('total_price')))
            ),
        ];
    }
}
