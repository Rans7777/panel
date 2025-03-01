<?php

namespace Tests\Feature;

use App\Filament\Pages\OrderPage;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_mount_initializes_cart_and_totalPrice()
    {
        session()->put('cart', [
            [
                'id' => 1,
                'name' => 'Test Product',
                'image' => 'test.jpg',
                'price' => 100,
                'quantity' => 2,
            ]
        ]);
        Product::create([
            'id'    => 1,
            'name'  => 'Test Product',
            'image' => 'test.jpg',
            'price' => 100,
            'stock' => 10,
        ]);
        $orderPage = new OrderPage();
        $orderPage->mount();
        $this->assertCount(1, $orderPage->cart);
        $this->assertEquals(200, $orderPage->totalPrice);
    }

    public function test_addToCart_success()
    {
        $product = Product::create([
            'name'  => 'Test Product',
            'image' => 'test.jpg',
            'price' => 150,
            'stock' => 5,
        ]);
        $orderPage = new OrderPage();
        $orderPage->mount();
        $orderPage->addToCart($product->id);
        $this->assertCount(1, $orderPage->cart);
        $this->assertEquals($product->id, $orderPage->cart[0]['id']);
        $this->assertEquals(1, $orderPage->cart[0]['quantity']);
        $this->assertEquals(150, $orderPage->totalPrice);
        $this->assertEquals($orderPage->cart, session('cart'));
    }

    public function test_addToCart_increments_quantity_if_item_exists()
    {
        $product = Product::create([
            'name'  => 'Test Product',
            'image' => 'test.jpg',
            'price' => 150,
            'stock' => 5,
        ]);
        $orderPage = new OrderPage();
        $orderPage->mount();
        $orderPage->addToCart($product->id);
        $orderPage->addToCart($product->id);
        $this->assertCount(1, $orderPage->cart);
        $this->assertEquals(2, $orderPage->cart[0]['quantity']);
        $this->assertEquals(300, $orderPage->totalPrice);
    }

    public function test_addToCart_does_not_add_when_stock_insufficient()
    {
        $product = Product::create([
            'name'  => 'Test Product',
            'image' => 'test.jpg',
            'price' => 100,
            'stock' => 1,
        ]);
        $orderPage = new OrderPage();
        $orderPage->mount();
        $orderPage->addToCart($product->id);
        $orderPage->addToCart($product->id);
        $this->assertCount(1, $orderPage->cart);
        $this->assertEquals(1, $orderPage->cart[0]['quantity']);
    }

    public function test_updateQuantity_updates_quantity_correctly()
    {
        $product = Product::create([
            'name'  => 'Test Product',
            'image' => 'test.jpg',
            'price' => 200,
            'stock' => 10,
        ]);
        $orderPage = new OrderPage();
        $orderPage->mount();
        $orderPage->addToCart($product->id);
        $orderPage->updateQuantity(0, 3);
        $this->assertEquals(3, $orderPage->cart[0]['quantity']);
        $this->assertEquals(600, $orderPage->totalPrice);
    }

    public function test_updateQuantity_removes_item_when_quantity_set_to_zero()
    {
        $product = Product::create([
            'name'  => 'Test Product',
            'image' => 'test.jpg',
            'price' => 200,
            'stock' => 10,
        ]);
        $orderPage = new OrderPage();
        $orderPage->mount();
        $orderPage->addToCart($product->id);
        $orderPage->updateQuantity(0, 0);
        $this->assertCount(0, $orderPage->cart);
        $this->assertEquals(0, $orderPage->totalPrice);
    }

    public function test_removeFromCart_removes_item_correctly()
    {
        $product = Product::create([
            'name'  => 'Test Product',
            'image' => 'test.jpg',
            'price' => 100,
            'stock' => 10,
        ]);
        $orderPage = new OrderPage();
        $orderPage->mount();
        $orderPage->addToCart($product->id);
        $this->assertCount(1, $orderPage->cart);
        $orderPage->removeFromCart(0);
        $this->assertCount(0, $orderPage->cart);
    }

    public function test_calculateChange_calculates_correctly()
    {
        $orderPage = new OrderPage();
        $orderPage->totalPrice = 500;
        $orderPage->paymentAmount = 800;
        $orderPage->calculateChange();
        $this->assertEquals(300, $orderPage->changeAmount);
    }

    public function test_updatedPaymentAmount_updates_correctly()
    {
        $orderPage = new OrderPage();
        $orderPage->updatedPaymentAmount(1000);
        $this->assertEquals(1000, $orderPage->paymentAmount);
        $orderPage->updatedPaymentAmount('');
        $this->assertEquals(0, $orderPage->paymentAmount);
    }

    public function test_confirmOrder_successful_order()
    {
        $product = Product::create([
            'name'  => 'Test Product',
            'image' => 'test.jpg',
            'price' => 250,
            'stock' => 5,
        ]);
        $orderPage = new OrderPage();
        $orderPage->mount();
        $orderPage->addToCart($product->id);
        $orderPage->paymentAmount = 250;
        $orderPage->confirmOrder();
        $this->assertEmpty($orderPage->cart);
        $this->assertFalse($orderPage->showPaymentPopup);
        $this->assertNull(session('cart'));
        $updatedProduct = Product::find($product->id);
        $this->assertEquals(4, $updatedProduct->stock);
        $this->assertDatabaseHas('orders', [
            'product_id'  => $product->id,
            'quantity'    => 1,
            'total_price' => 250,
        ]);
    }

    public function test_confirmOrder_fails_with_empty_cart()
    {
        $orderPage = new OrderPage();
        $orderPage->cart = [];
        $orderPage->paymentAmount = 0;
        $orderPage->confirmOrder();
        $this->assertEmpty($orderPage->cart);
    }

    public function test_confirmOrder_fails_with_insufficient_payment()
    {
        $product = Product::create([
            'name'  => 'Test Product',
            'image' => 'test.jpg',
            'price' => 300,
            'stock' => 5,
        ]);
        $orderPage = new OrderPage();
        $orderPage->mount();
        $orderPage->addToCart($product->id);
        $orderPage->paymentAmount = 200;
        $orderPage->confirmOrder();
        $updatedProduct = Product::find($product->id);
        $this->assertEquals(5, $updatedProduct->stock);
        $this->assertDatabaseMissing('orders', [
            'product_id' => $product->id,
        ]);
    }

    public function test_handleProductClick_shows_options_popup_when_options_exist()
    {
        $product = Product::create([
            'name' => 'Test Product with Options',
            'image' => 'test.jpg',
            'price' => 200,
            'stock' => 10,
        ]);
        $product->options()->create([
            'option_name' => 'Extra Cheese',
            'price' => 50,
        ]);
        $orderPage = new OrderPage();
        $orderPage->mount();
        $orderPage->handleProductClick($product->id);
        $this->assertTrue($orderPage->showOptionsPopup);
        $this->assertEquals($product->id, $orderPage->selectedProductId);
        $this->assertCount(1, $orderPage->selectedProductOptions);
    }

    public function test_confirmOptionSelection_adds_product_with_options()
    {
        $product = Product::create([
            'name' => 'Test Product with Options',
            'image' => 'test.jpg',
            'price' => 200,
            'stock' => 10,
        ]);
        $option = $product->options()->create([
            'option_name' => 'Extra Cheese',
            'price' => 50,
        ]);
        $orderPage = new OrderPage();
        $orderPage->mount();
        $orderPage->handleProductClick($product->id);
        $orderPage->selectedOptionIds = [$option->id];
        $orderPage->confirmOptionSelection();
        $this->assertCount(1, $orderPage->cart);
        $this->assertEquals($product->id, $orderPage->cart[0]['id']);
        $this->assertEquals(1, $orderPage->cart[0]['quantity']);
        $this->assertEquals(250, $orderPage->cart[0]['price']);
        $this->assertArrayHasKey('options', $orderPage->cart[0]);
        $this->assertEquals(session('cart'), $orderPage->cart);
    }

    public function test_cancelOptionSelection_resets_selection()
    {
        $orderPage = new OrderPage();
        $orderPage->selectedProductId = 1;
        $orderPage->selectedProductOptions = [['id' => 1, 'option_name' => 'Extra Cheese', 'price' => 50]];
        $orderPage->selectedOptionIds = [1];
        $orderPage->showOptionsPopup = true;
        $orderPage->cancelOptionSelection();
        $this->assertNull($orderPage->selectedProductId);
        $this->assertEmpty($orderPage->selectedProductOptions);
        $this->assertEmpty($orderPage->selectedOptionIds);
        $this->assertFalse($orderPage->showOptionsPopup);
    }
}
