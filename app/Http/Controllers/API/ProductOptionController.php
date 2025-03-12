<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ProductOption;

class ProductOptionController extends Controller
{
    public function index()
    {
        $options = ProductOption::all();
        return response()->json($options);
    }
}
