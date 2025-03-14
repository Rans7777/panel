<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'cart' => 'required|array',
                'cart.*.id' => 'required|exists:products,id',
                'cart.*.uuid' => 'required|uuid',
                'cart.*.quantity' => 'required|integer|min:1',
                'cart.*.price' => 'required|numeric|min:0',
                'cart.*.options' => 'nullable|array',
                'paymentAmount' => 'required|numeric|min:0',
                'changeAmount' => 'required|numeric|min:0',
            ]);
            DB::beginTransaction();
            foreach ($validatedData['cart'] as $item) {
                $product = Product::findOrFail($item['id']);
                if ($product->stock < $item['quantity']) {
                    DB::rollBack();

                    return response()->make(status: 400);
                }
                $product->decrement('stock', $item['quantity']);
                Order::create([
                    'product_id' => $item['id'],
                    'uuid' => $item['uuid'],
                    'quantity' => $item['quantity'],
                    'image' => $item['image'] ?? null,
                    'total_price' => $item['price'] * $item['quantity'],
                    'options' => isset($item['options']) ? json_encode($item['options']) : null,
                ]);
            }
            DB::commit();

            return response()->make(status: 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            Log::error($e);

            return response()->make(status: 422);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);

            return response()->make(status: 500);
        }
    }
}
