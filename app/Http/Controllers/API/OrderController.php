<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'cart' => 'required|array',
                'cart.*.id' => 'required|exists:products,id',
                'cart.*.quantity' => 'required|integer|min:1',
                'cart.*.price' => 'required|numeric|min:0',
                'paymentAmount' => 'required|numeric|min:0',
                'changeAmount' => 'required|numeric|min:0',
            ]);
            DB::beginTransaction();
            $orders = [];
            foreach ($validatedData['cart'] as $item) {
                $product = Product::findOrFail($item['id']);
                if ($product->stock < $item['quantity']) {
                    DB::rollBack();

                    return response()->json([
                        'message' => '在庫不足: ' . $product->name,
                    ], 400);
                }
                $product->decrement('stock', $item['quantity']);
                $order = Order::create([
                    'product_id' => $item['id'],
                    'quantity' => $item['quantity'],
                    'image' => $item['image'] ?? null,
                    'total_price' => $item['price'] * $item['quantity'],
                    'options' => isset($item['options']) ? json_encode($item['options']) : null,
                ]);
                $orders[] = $order;
            }
            DB::commit();

            return response()->make(status: 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('注文処理エラー: ' . $e->getMessage());

            return response()->make(status: 500);
        }
    }
}
