<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_products_below_the_configured_threshold(): void
    {
        config(['inventory.low_stock_threshold' => 10]);

        Product::factory()->create(['stock' => 3]);
        Product::factory()->create(['stock' => 9]);
        Product::factory()->create(['stock' => 10]); // threshold-ku equal -> low illa
        Product::factory()->create(['stock' => 50]);

        $this->getJson('/api/products/low-stock')
            ->assertOk()
            ->assertJsonPath('threshold', 10)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.stock', 3); // kuraincha stock first
    }

    public function test_threshold_can_be_overridden_per_request(): void
    {
        Product::factory()->create(['stock' => 3]);
        Product::factory()->create(['stock' => 30]);

        $this->getJson('/api/products/low-stock?threshold=100')->assertJsonCount(2, 'data');
        $this->getJson('/api/products/low-stock?threshold=5')->assertJsonCount(1, 'data');
    }
}