<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderFeatureTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    public function setUp(): void
    {
        parent::setUp();
        // Create a default user and set up authentication
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    #[Test]
    public function it_can_create_an_order()
    {
        // Create 2 products with stock
        $product1 = Product::factory()->create(['stock' => 5]);
        $product2 = Product::factory()->create(['stock' => 10]);

        // Prepare the order data
        $data = [
            'products' => [
                ['product_id' => $product1->id, 'quantity' => 3],
                ['product_id' => $product2->id, 'quantity' => 5],
            ]
        ];

        $response = $this->postJson('/api/v1/orders', $data);

        $response->assertStatus(201)
                 ->assertJsonStructure(['data' => ['id', 'products', 'created_at']]);

        // Ensure the order and product pivot table data are correctly stored
        $this->assertDatabaseHas('orders', ['user_id' => $this->user->id]);
        $this->assertDatabaseHas('order_product', [
            'product_id' => $product1->id,
            'quantity' => 3
        ]);
        $this->assertDatabaseHas('order_product', [
            'product_id' => $product2->id,
            'quantity' => 5
        ]);

        $this->assertEquals(2, $product1->fresh()->stock);
        $this->assertEquals(5, $product2->fresh()->stock);
    }

    #[Test]
    public function it_validates_stock_before_creating_order()
    {
        $product = Product::factory()->create(['stock' => 2]);

        // Try to order more than available stock
        $data = [
            'products' => [
                ['product_id' => $product->id, 'quantity' => 5],
            ]
        ];

        $response = $this->postJson('/api/v1/orders', $data);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors("products.0.quantity");

        $this->assertDatabaseMissing('orders', ['user_id' => $this->user->id]);
    }

    #[Test]
    public function it_can_update_an_order_and_adjust_stock_correctly()
    {
        // Create 4 products with stock and an order
        $product1 = Product::factory()->create(['stock' => 10]);
        $product2 = Product::factory()->create(['stock' => 20]);
        $product3 = Product::factory()->create(['stock' => 30]);
        $product4 = Product::factory()->create(['stock' => 40]);

        $orderData = [
            'products' => [
                ['product_id' => $product1->id, 'quantity' => 5],
                ['product_id' => $product2->id, 'quantity' => 10],
                ['product_id' => $product3->id, 'quantity' => 15],
                ['product_id' => $product4->id, 'quantity' => 20],
            ]
        ];

        $order = $this->postJson('/api/v1/orders', $orderData)->assertStatus(201);

        $data = [
            'products' => [
                ['product_id' => $product1->id, 'quantity' => 5],  // Same quantity
                ['product_id' => $product2->id, 'quantity' => 15],  // Increased quantity
                ['product_id' => $product3->id, 'quantity' => 10],  // Decreased quantity
                // Remove product4
            ]
        ];

        $orderId = $order->json('data.id');
        $response = $this->putJson("/api/v1/orders/{$orderId}", $data);

        $response->assertStatus(200)
                 ->assertJsonStructure(['data' => ['id', 'products']]);

        // Assert that stock was adjusted correctly
        $this->assertEquals(5, $product1->fresh()->stock);
        $this->assertEquals(5, $product2->fresh()->stock);
        $this->assertEquals(20, $product3->fresh()->stock);
        $this->assertEquals(40, $product4->fresh()->stock);  // Stock restored for removed product4
    }

    #[Test]
    public function it_can_delete_an_order_and_restore_stock()
    {
        $product = Product::factory()->create(['stock' => 10]);

        $orderData = [
            'products' => [
                ['product_id' => $product->id, 'quantity' => 5],
            ]
        ];
        $this->postJson('/api/v1/orders', $orderData)->assertStatus(201);

        $order = Order::latest()->first();

        $response = $this->deleteJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(204);

        // Ensure the order is deleted
        $this->assertSoftDeleted($order);

        // Ensure stock is restored
        $this->assertEquals(10, $product->fresh()->stock);
    }
}
