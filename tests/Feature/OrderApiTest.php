<?php

namespace Tests\Feature;

use App\Jobs\SendOrderConfirmation;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function placeOrder(array $items, string $email = 'thomas@example.com', array $extra = [])
    {
        return $this->postJson('/api/orders', array_merge([
            'customer_email' => $email,
            'customer_name' => 'Thomas',
            'items' => $items,
        ], $extra));
    }

    public function test_it_creates_an_order_computes_totals_and_deducts_stock(): void
    {
        $toothpaste = Product::factory()->create(['price' => 50, 'tax_percent' => 8, 'stock' => 10]);
        $biscuit = Product::factory()->create(['price' => 10, 'tax_percent' => 8, 'stock' => 20]);

        $response = $this->placeOrder([
            ['product_id' => $toothpaste->id, 'quantity' => 2],
            ['product_id' => $biscuit->id, 'quantity' => 5],
        ], extra: ['amount_given' => 200]);

        $response->assertCreated()->assertJsonCount(2, 'data.items');

        $this->assertEquals(150.0, $response->json('data.subtotal'));
        $this->assertEquals(12.0, $response->json('data.tax_total'));
        $this->assertEquals(162.0, $response->json('data.grand_total'));
        $this->assertEquals(38.0, $response->json('data.balance_returned'));

        $this->assertSame(8, $toothpaste->fresh()->stock);
        $this->assertSame(15, $biscuit->fresh()->stock);
        $this->assertDatabaseCount('order_items', 2);
        Queue::assertPushed(SendOrderConfirmation::class, 1);
    }

    public function test_it_rejects_the_order_when_stock_is_insufficient_and_changes_nothing(): void
    {
        $product = Product::factory()->create(['stock' => 1]);

        $this->placeOrder([['product_id' => $product->id, 'quantity' => 2]])
            ->assertStatus(422)
            ->assertJsonPath('available', 1)
            ->assertJsonPath('requested', 2);

        $this->assertSame(1, $product->fresh()->stock);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('customers', 0); // transaction rollback aachu
        Queue::assertNothingPushed();
    }

    public function test_second_order_for_the_last_unit_fails_cleanly(): void
    {
        $product = Product::factory()->create(['stock' => 1]);
        $items = [['product_id' => $product->id, 'quantity' => 1]];

        $this->placeOrder($items, 'first@example.com')->assertCreated();
        $this->placeOrder($items, 'second@example.com')->assertStatus(422);

        $this->assertSame(0, $product->fresh()->stock);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_it_rejects_unknown_products(): void
    {
        $this->placeOrder([['product_id' => 9999, 'quantity' => 1]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.product_id']);
    }

    public function test_it_rejects_invalid_quantities_and_empty_items(): void
    {
        $product = Product::factory()->create(['stock' => 5]);

        $this->placeOrder([['product_id' => $product->id, 'quantity' => 0]])
            ->assertJsonValidationErrors(['items.0.quantity']);

        $this->placeOrder([])->assertJsonValidationErrors(['items']);
    }

    public function test_new_customer_requires_a_name_but_existing_customer_does_not(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $items = [['product_id' => $product->id, 'quantity' => 1]];

        $this->postJson('/api/orders', ['customer_email' => 'new@example.com', 'items' => $items])
            ->assertJsonValidationErrors(['customer_name']);

        Customer::factory()->create(['email' => 'old@example.com', 'name' => 'Old Customer']);

        $this->postJson('/api/orders', ['customer_email' => 'OLD@example.com', 'items' => $items])
            ->assertCreated()
            ->assertJsonPath('data.customer.name', 'Old Customer');

        $this->assertDatabaseCount('customers', 1);
    }

    public function test_duplicate_product_lines_are_merged(): void
    {
        $product = Product::factory()->create(['stock' => 10]);

        $this->placeOrder([
            ['product_id' => $product->id, 'quantity' => 1],
            ['product_id' => $product->id, 'quantity' => 2],
        ])->assertCreated()->assertJsonCount(1, 'data.items');

        $this->assertDatabaseHas('order_items', ['product_id' => $product->id, 'quantity' => 3]);
        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_it_rejects_amount_given_below_grand_total(): void
    {
        $product = Product::factory()->create(['price' => 100, 'tax_percent' => 0, 'stock' => 5]);

        $this->placeOrder([['product_id' => $product->id, 'quantity' => 1]], extra: ['amount_given' => 50])
            ->assertJsonValidationErrors(['amount_given']);

        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_it_returns_order_history_for_a_customer_email(): void
    {
        $product = Product::factory()->create(['stock' => 50]);
        $items = [['product_id' => $product->id, 'quantity' => 1]];

        $this->placeOrder($items, 'thomas@example.com');
        $this->placeOrder($items, 'thomas@example.com');
        $this->placeOrder($items, 'other@example.com');

        $this->getJson('/api/orders?email=thomas@example.com')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.customer.email', 'thomas@example.com');
    }

    public function test_order_history_returns_404_for_unknown_email(): void
    {
        $this->getJson('/api/orders?email=nobody@example.com')->assertNotFound();
    }
}