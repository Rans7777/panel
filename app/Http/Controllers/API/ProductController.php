<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('options')->get();
        $products = $products->map(function ($product) {
            $product->setAttribute('has_options', $product->options->count() > 0);

            return $product;
        });

        return response()->json($products);
    }

    public function show($id)
    {
        $product = Product::with('options')->findOrFail($id);
        $product->setAttribute('has_options', $product->options->count() > 0);

        return response()->json($product);
    }
}
