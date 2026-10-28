<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer' => [
                'name' => $this->customer->name,
                'email' => $this->customer->email,
            ],
            'items' => $this->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_name' => $item->product->name,
                'code' => $item->product->code,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'tax_percent' => (float) $item->tax_percent,
                'line_subtotal' => (float) $item->line_subtotal,
                'line_tax' => (float) $item->line_tax,
                'line_total' => (float) $item->line_total,
            ])->values(),
            'subtotal' => (float) $this->subtotal,
            'tax_total' => (float) $this->tax_total,
            'grand_total' => (float) $this->grand_total,
            'amount_given' => $this->amount_given !== null ? (float) $this->amount_given : null,
            'balance_returned' => $this->balance_returned !== null ? (float) $this->balance_returned : null,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}