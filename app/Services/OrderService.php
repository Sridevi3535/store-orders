<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Jobs\SendOrderConfirmation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function create(array $data): Order
    {
        $order = DB::transaction(function () use ($data) {
            // Same product rendu line-la vandha, onnaa merge pannidrom
            $quantities = collect($data['items'])
                ->groupBy('product_id')
                ->map(fn ($rows) => (int) $rows->sum('quantity'));

            $customer = Customer::where('email', $data['customer_email'])->first()
                ?? Customer::createOrFirst(
                    ['email' => $data['customer_email']],
                    ['name' => $data['customer_name']]
                );

            
            $products = Product::whereIn('id', $quantities->keys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lines = [];
            $subtotal = 0.0;
            $taxTotal = 0.0;

            foreach ($quantities as $productId => $quantity) {
                $product = $products->get($productId);

                if ($product->stock < $quantity) {
                    throw new InsufficientStockException($product, $quantity);
                }

                $lineSubtotal = round((float) $product->price * $quantity, 2);
                $lineTax = round($lineSubtotal * (float) $product->tax_percent / 100, 2);

                $lines[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                    'tax_percent' => $product->tax_percent,
                    'line_subtotal' => $lineSubtotal,
                    'line_tax' => $lineTax,
                    'line_total' => round($lineSubtotal + $lineTax, 2),
                ];

                $subtotal += $lineSubtotal;
                $taxTotal += $lineTax;

                $product->decrement('stock', $quantity); // atomic: stock = stock - n
            }

            $subtotal = round($subtotal, 2);
            $taxTotal = round($taxTotal, 2);
            $grandTotal = round($subtotal + $taxTotal, 2);

            $amountGiven = $data['amount_given'] ?? null;
            if ($amountGiven !== null && (float) $amountGiven < $grandTotal) {
                throw ValidationException::withMessages([
                    'amount_given' => 'Amount given is less than the grand total ('.number_format($grandTotal, 2).').',
                ]);
            }

            $order = $customer->orders()->create([
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'grand_total' => $grandTotal,
                'amount_given' => $amountGiven,
                'balance_returned' => $amountGiven !== null
                    ? round((float) $amountGiven - $grandTotal, 2)
                    : null,
            ]);

            $order->items()->createMany($lines);

            return $order;
        });

        // Transaction commit aana apparam thaan job dispatch (rollback-ku mail poga koodathu)
        SendOrderConfirmation::dispatch($order);

        return $order->load('customer', 'items.product');
    }
}