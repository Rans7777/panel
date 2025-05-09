<?php

namespace Tests\Feature\Http\Controllers\API;

use Tests\TestCase;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_unauthenticated_user_cannot_access_products()
    {
        $response = $this->getJson('/api/products');
        $response->assertStatus(401);
    }

    public function testIndexReturnsProducts()
    {
        $productWithoutOption = Product::factory()->create();
        $productWithOption = Product::factory()->create();
        $productWithOption->options()->create([
            'option_name' => 'Test Option',
            'price' => 1000,
        ]);
        $response = $this->actingAs($this->user)->getJson('/api/products');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'has_options'
                ]
            ]
        ]);
        $responseData = $response->json();
        $products = $responseData['data'];
        $foundWithout = false;
        $foundWith = false;
        foreach ($products as $product) {
            if ($product['id'] === $productWithoutOption->id) {
                $this->assertFalse($product['has_options'], 'If product has no options, has_options should be false');
                $foundWithout = true;
            } elseif ($product['id'] === $productWithOption->id) {
                $this->assertTrue($product['has_options'], 'If product has options, has_options should be true');
                $foundWith = true;
            }
        }
        $this->assertTrue($foundWithout, 'Product without options not found');
        $this->assertTrue($foundWith, 'Product with options not found');
    }

    public function testShowReturnsProduct()
    {
        $product = Product::factory()->create();
        $response = $this->actingAs($this->user)->getJson("/api/products/{$product->id}");
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'has_options'
            ]
        ]);
        $responseData = $response->json();
        $this->assertFalse($responseData['data']['has_options'], 'If no options set, has_options should be false');
    }

    public function test_unauthenticated_user_cannot_access_single_product()
    {
        $product = Product::factory()->create();
        $response = $this->getJson("/api/products/{$product->id}");
        $response->assertStatus(401);
    }
}
