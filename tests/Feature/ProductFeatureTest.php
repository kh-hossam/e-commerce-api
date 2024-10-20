<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductFeatureTest extends TestCase
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
    public function it_lists_all_products_with_filters()
    {
        // Create products
        Product::factory()->create(['name' => 'Test Product 1', 'price' => 50]);
        Product::factory()->create(['name' => 'Another Product', 'price' => 150]);

        // Test without filters
        $response = $this->getJson('/api/v1/products');
        $response->assertStatus(200)->assertJsonCount(2, 'data');

        // Test with name filter
        $response = $this->getJson('/api/v1/products?name=Test');
        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertEquals('Test Product 1', $response->json('data.0.name'));

        // Test with price_min filter
        $response = $this->getJson('/api/v1/products?price_min=100');
        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertEquals('Another Product', $response->json('data.0.name'));

        // Test with price_max filter
        $response = $this->getJson('/api/v1/products?price_max=100');
        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertEquals('Test Product 1', $response->json('data.0.name'));

        // Test with combined filters
        $response = $this->getJson('/api/v1/products?name=Another&price_min=100&price_max=200');
        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertEquals('Another Product', $response->json('data.0.name'));
    }

    #[Test]
    public function it_creates_a_product()
    {
        $category = Category::factory()->create();

        $payload = [
            'name' => 'New Product',
            'price' => 200,
            'stock' => 30,
            'category_id' => $category->id,
        ];

        $response = $this->postJson('/api/v1/products', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('products', ['name' => 'New Product']);
        $response->assertJsonFragment(['name' => 'New Product']);
    }

    #[Test]
    public function it_shows_a_product()
    {
        $product = Product::factory()->create();

        $response = $this->getJson('/api/v1/products/' . $product->id);

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => $product->name]);
    }

    #[Test]
    public function it_updates_a_product()
    {
        $product = Product::factory()->create(['name' => 'Old Name', 'price' => 100]);

        $payload = [
            'name' => 'Updated Name',
            'price' => 150,
            'stock' => 25,
            'category_id' => 1,
        ];

        $response = $this->putJson('/api/v1/products/' . $product->id, $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('products', ['name' => 'Updated Name', 'price' => 150]);
    }

    #[Test]
    public function it_deletes_a_product()
    {
        $product = Product::factory()->create();

        $response = $this->deleteJson('/api/v1/products/' . $product->id);

        $response->assertStatus(204);
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }
}
