<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOrderConfirmation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(public Order $order)
    {
    }

    public function handle(): void
    {
        $order = $this->order->loadMissing('customer', 'items.product');

        // Real SMTP vendaam nu sonnaanga, so log-la simulate pannurom
        Log::info('Order confirmation email sent (simulated)', [
            'order_id' => $order->id,
            'to' => $order->customer->email,
            'grand_total' => (float) $order->grand_total,
            'items' => $order->items->map(fn ($i) => "{$i->product->name} x {$i->quantity}")->all(),
        ]);
    }
}