<?php

namespace Tests\Unit;

use App\Events\OrderPlaced;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    protected OrderService $orderService;
    protected User $user;

    public function setUp(): void
    {
        parent::setUp();
        $this->orderService = new OrderService();
        $this->user = User::factory()->create();
    }

    #[Test]
    public function it_can_place_an_order_with_valid_products_and_adjust_stock()
    {
        Event::fake();

        $product1 = Product::factory()->create(['stock' => 5]);
        $product2 = Product::factory()->create(['stock' => 10]);

        $products = [
            ['product_id' => $product1->id, 'quantity' => 3],
            ['product_id' => $product2->id, 'quantity' => 5],
        ];

        $order = $this->orderService->placeOrder($this->user, $products);

        $this->assertDatabaseHas('orders', ['user_id' => $this->user->id]);
        $this->assertDatabaseHas('order_product', [
            'order_id' => $order->id,
            'product_id' => $product1->id,
            'quantity' => 3,
        ]);
        $this->assertDatabaseHas('order_product', [
            'order_id' => $order->id,
            'product_id' => $product2->id,
            'quantity' => 5,
        ]);
        $this->assertEquals(2, $product1->fresh()->stock);
        $this->assertEquals(5, $product2->fresh()->stock);

        Event::assertDispatched(OrderPlaced::class);
    }

    #[Test]
    public function it_throws_validation_exception_if_stock_is_insufficient()
    {
        $product = Product::factory()->create(['stock' => 2]);

        $this->expectException(ValidationException::class);

        $this->orderService->placeOrder($this->user, [
            ['product_id' => $product->id, 'quantity' => 5]
        ]);
    }

    #[Test]
    public function it_updates_existing_product_quantities_and_adjusts_stock()
    {
        $product1 = Product::factory()->create(['stock' => 10]);
        $product2 = Product::factory()->create(['stock' => 20]);
        $product3 = Product::factory()->create(['stock' => 30]);
        $product4 = Product::factory()->create(['stock' => 40]);

        $order = $this->orderService->placeOrder($this->user, [
            ['product_id' => $product1->id, 'quantity' => 3],
            ['product_id' => $product2->id, 'quantity' => 15],
            ['product_id' => $product3->id, 'quantity' => 20],
            ['product_id' => $product4->id, 'quantity' => 3],
        ]);

        $this->orderService->updateOrder($order, [
            ['product_id' => $product1->id, 'quantity' => 5], // increase quantity
            ['product_id' => $product2->id, 'quantity' => 10], // decrease quantity
            ['product_id' => $product3->id, 'quantity' => 20], // same quantity
            // remove product4
        ]);

        $product1PivotQuantity = $product1->orders->firstWhere('id', $order->id)->pivot->quantity;
        $product2PivotQuantity = $product2->orders->firstWhere('id', $order->id)->pivot->quantity;
        $product3PivotQuantity = $product3->orders->firstWhere('id', $order->id)->pivot->quantity;

        $this->assertEquals(5, $product1PivotQuantity);  // Order quantity updated
        $this->assertEquals(5, $product1->fresh()->stock);  // Product Stock adjusted

        $this->assertEquals(10, $product2PivotQuantity);
        $this->assertEquals(10, $product2->fresh()->stock);

        $this->assertEquals(20, $product3PivotQuantity);
        $this->assertEquals(10, $product3->fresh()->stock);

        $this->assertNull($product4->orders->firstWhere('id', $order->id));
        $this->assertEquals(40, $product4->fresh()->stock);
    }

    #[Test]
    public function it_removes_product_from_order_and_restores_stock()
    {
        $product = Product::factory()->create(['stock' => 10]);

        $order = $this->orderService->placeOrder($this->user, [
            ['product_id' => $product->id, 'quantity' => 3]
        ]);

        $this->orderService->updateOrder($order, []);

        $this->assertDatabaseMissing('order_product', ['order_id' => $order->id, 'product_id' => $product->id]);
        $this->assertEquals(10, $product->fresh()->stock);  // Stock restored
    }

    #[Test]
    public function it_deletes_an_order_and_restores_all_product_stock()
    {
        $product = Product::factory()->create(['stock' => 10]);

        $order = $this->orderService->placeOrder($this->user, [
            ['product_id' => $product->id, 'quantity' => 5]
        ]);

        $this->orderService->deleteOrder($order);

        $this->assertSoftDeleted($order);
        $this->assertEquals(10, $product->fresh()->stock);  // Stock restored
    }
}

