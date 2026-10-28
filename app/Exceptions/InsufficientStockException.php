<?php

namespace App\Exceptions;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(public readonly Product $product, public readonly int $requested)
    {
        parent::__construct(
            "Insufficient stock for {$product->name}: requested {$requested}, available {$product->stock}."
        );
    }

    // Laravel idha automatic-a call pannum -> clean 422 JSON
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'product_id' => $this->product->id,
            'requested' => $this->requested,
            'available' => $this->product->stock,
        ], 422);
    }
}