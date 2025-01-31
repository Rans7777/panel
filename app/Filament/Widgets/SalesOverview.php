<?php

namespace App\Filament\Widgets;

use App\Models\Orders;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;

class SalesOverview extends BaseWidget
{
    protected function getCards(): array
    {
        return [
            Card::make('今日の売上', '¥' . number_format(Orders::whereDate('order_date', now()->toDateString())->sum('total_price'))),
            Card::make('総売上', '¥' . number_format(Orders::sum('total_price'))),
        ];
    }
}
