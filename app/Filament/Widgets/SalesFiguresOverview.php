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
            ->select('products.name')
            ->groupBy('products.name')
            ->get()
            ->map(function ($product) {
                $totalQuantity = Order::join('products', 'orders.product_id', '=', 'products.id')
                    ->where('products.name', $product->name)
                    ->sum('orders.quantity');
                $buyerCount = Order::join('products', 'orders.product_id', '=', 'products.id')
                    ->where('products.name', $product->name)
                    ->count();
                return Stat::make($product->name, "売上数: $totalQuantity")
                    ->description("購入者数: $buyerCount 人");
            })
            ->toArray();

        return $productSales;
    }
}
