<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesFiguresOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $productSales = Order::join('products', 'orders.product_id', '=', 'products.id')
            ->selectRaw('products.name, SUM(orders.quantity) as total_quantity, COUNT(DISTINCT orders.id) as buyer_count')
            ->groupBy('products.name')
            ->get()
            /** @phpstan-ignore-next-line */
            ->map(fn($product) => Stat::make($product->name, "売上数: {$product->total_quantity}")
                ->description("購入者数: {$product->buyer_count} 人")) /** @phpstan-ignore-line */
            ->toArray();

        return $productSales;
    }
}
