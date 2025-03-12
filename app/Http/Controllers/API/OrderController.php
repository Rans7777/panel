<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'product_id'  => 'required|exists:products,id',
            'quantity'    => 'required|integer|min:1',
            'options'     => 'nullable|array',
        ]);

        $order = Order::create($validatedData);

        return response()->json([
            'message' => '注文が正常に作成されました',
            'order'   => $order,
        ], 201);
    }
} 