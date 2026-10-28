<?php

namespace Tests\Feature;

use App\Jobs\SendOrderConfirmation;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class SendOrderConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_logs_a_simulated_confirmation_email(): void
    {
        Queue::fake();
        $product = Product::factory()->create(['stock' => 5]);

        $this->postJson('/api/orders', [
            'customer_email' => 'thomas@example.com',
            'customer_name' => 'Thomas',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        Log::shouldReceive('info')
            ->once()
            ->with('Order confirmation email sent (simulated)', Mockery::type('array'));

        (new SendOrderConfirmation(Order::firstOrFail()))->handle();
    }
}