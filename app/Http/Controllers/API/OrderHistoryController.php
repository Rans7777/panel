<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class OrderHistoryController extends Controller
{
    public function index(): JsonResponse
    {
        $orderHistory = Order::with(['product'])
            ->select('orders.*')
            ->selectRaw('(orders.quantity * products.price) as total_amount')
            ->join('products', 'orders.product_id', '=', 'products.id')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($orderHistory);
    }
}
