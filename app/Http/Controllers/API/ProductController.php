<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('options')->get(); /** @phpstan-ignore-line */
        $products = $products->map(function ($product) {
            $product->setAttribute('has_options', $product->options->count() > 0); /** @phpstan-ignore-line */

            return $product;
        });

        return response()->json([
            'data' => $products,
            'meta' => [
                'total' => $products->count(),
            ],
        ]);
    }

    public function show($id)
    {
        $product = Product::with('options')->findOrFail($id); /** @phpstan-ignore-line */
        $product->setAttribute('has_options', $product->options->count() > 0); /** @phpstan-ignore-line */

        return response()->json([
            'data' => $product,
            'meta' => [
                'total' => $product->count(),
            ],
        ]);
    }
}
