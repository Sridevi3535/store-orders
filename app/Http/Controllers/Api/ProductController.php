<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Product::orderBy('name')->get()]);
    }

    // GET /api/products/low-stock?threshold=15 (optional, default config-la irundhu)
    public function lowStock(Request $request): JsonResponse
    {
        $validated = $request->validate(['threshold' => ['nullable', 'integer', 'min:1']]);
        $threshold = (int) ($validated['threshold'] ?? config('inventory.low_stock_threshold'));

        return response()->json([
            'threshold' => $threshold,
            'data' => Product::lowStock($threshold)->orderBy('stock')->get(),
        ]);
    }
}