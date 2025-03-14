<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Models\Product;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Str;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;
    private Product $product;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->product = Product::factory()->create([
            'stock' => 10,
            'price' => 1000
        ]);
    }

    public function test_unauthenticated_user_cannot_access_api()
    {
        $payload = [
            'cart' => [
                [
                    'id' => $this->product->id,
                    'uuid' => Str::uuid(),
                    'quantity' => 2,
                    'price' => 1000,
                ]
            ],
            'paymentAmount' => 2000,
            'changeAmount' => 0,
        ];
        $response = $this->postJson('/api/orders', $payload);
        $response->assertStatus(401);
    }

    public function test_can_create_a_new_order_successfully()
    {
        $payload = [
            'cart' => [
                [
                    'id' => $this->product->id,
                    'uuid' => Str::uuid(),
                    'quantity' => 2,
                    'price' => 1000,
                ]
            ],
            'paymentAmount' => 2000,
            'changeAmount' => 0,
        ];
        $response = $this->actingAs($this->user)->postJson('/api/orders', $payload);
        $response->assertStatus(201);
        $this->assertEquals(1, Order::count());
        $this->assertEquals(8, $this->product->fresh()->stock);
    }

    public function test_returns_error_when_product_stock_is_insufficient()
    {
        $payload = [
            'cart' => [
                [
                    'id' => $this->product->id,
                    'uuid' => Str::uuid(),
                    'quantity' => 20,
                    'price' => 1000,
                ]
            ],
            'paymentAmount' => 20000,
            'changeAmount' => 0,
        ];
        $response = $this->actingAs($this->user)->postJson('/api/orders', $payload);
        $response->assertStatus(400);
        $this->assertEquals(0, Order::count());
        $this->assertEquals(10, $this->product->fresh()->stock);
    }

    public function test_validates_required_fields()
    {
        $response = $this->actingAs($this->user)->postJson('/api/orders', []);
        $response->assertStatus(422);
    }

    public function test_can_create_order_with_options()
    {
        $payload = [
            'cart' => [
                [
                    'id' => $this->product->id,
                    'uuid' => Str::uuid(),
                    'quantity' => 1,
                    'price' => 1000,
                    'options' => ['size' => 'L', 'color' => 'red']
                ]
            ],
            'paymentAmount' => 1000,
            'changeAmount' => 0,
        ];
        $response = $this->actingAs($this->user)->postJson('/api/orders', $payload);
        $response->assertStatus(201);
        $order = Order::first();
        $this->assertEquals(['size' => 'L', 'color' => 'red'], json_decode($order->options, true));
    }
}
