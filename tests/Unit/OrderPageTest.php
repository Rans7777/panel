<?php

use App\Filament\Pages\OrderPage;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('mount initializes cart and totalPrice', function () {
    session()->put('cart', [
        [
            'id'       => 1,
            'name'     => 'Test Product',
            'image'    => 'test.jpg',
            'price'    => 100,
            'quantity' => 2,
        ],
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

    expect($orderPage->cart)->toHaveCount(1);
    expect($orderPage->totalPrice)->toBe(200);
});

test('addToCart successfully adds a product', function () {
    $product = Product::create([
        'name'  => 'Test Product',
        'image' => 'test.jpg',
        'price' => 150,
        'stock' => 5,
    ]);

    $orderPage = new OrderPage();
    $orderPage->mount();
    $orderPage->addToCart($product->id);

    expect($orderPage->cart)->toHaveCount(1);
    expect($orderPage->cart[0]['id'])->toBe($product->id);
    expect($orderPage->cart[0]['quantity'])->toBe(1);
    expect($orderPage->totalPrice)->toBe(150);
    expect($orderPage->cart)->toBe(session('cart'));
});

test('addToCart increases the quantity if the product already exists', function () {
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

    expect($orderPage->cart)->toHaveCount(1);
    expect($orderPage->cart[0]['quantity'])->toBe(2);
    expect($orderPage->totalPrice)->toBe(300);
});

test('addToCart does not add a product when the stock is insufficient', function () {
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

    expect($orderPage->cart)->toHaveCount(1);
    expect($orderPage->cart[0]['quantity'])->toBe(1);
});

test('updateQuantity correctly updates the quantity', function () {
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

    expect($orderPage->cart[0]['quantity'])->toBe(3);
    expect($orderPage->totalPrice)->toBe(600);
});

test('updateQuantity removes the item when its quantity is set to 0', function () {
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

    expect($orderPage->cart)->toBeEmpty();
    expect($orderPage->totalPrice)->toBe(0);
});

test('removeFromCart correctly removes an item', function () {
    $product = Product::create([
        'name'  => 'Test Product',
        'image' => 'test.jpg',
        'price' => 100,
        'stock' => 10,
    ]);

    $orderPage = new OrderPage();
    $orderPage->mount();
    $orderPage->addToCart($product->id);

    expect($orderPage->cart)->toHaveCount(1);
    $orderPage->removeFromCart(0);
    expect($orderPage->cart)->toBeEmpty();
});

test('calculateChange correctly calculates the change', function () {
    $orderPage = new OrderPage();
    $orderPage->totalPrice = 500;
    $orderPage->paymentAmount = 800;
    $orderPage->calculateChange();

    expect($orderPage->changeAmount)->toBe(300);
});

test('updatedPaymentAmount correctly updates the payment amount', function () {
    $orderPage = new OrderPage();
    $orderPage->updatedPaymentAmount(1000);
    expect($orderPage->paymentAmount)->toBe(1000);
    $orderPage->updatedPaymentAmount('');
    expect($orderPage->paymentAmount)->toBe(0);
});

test('confirmOrder successfully confirms the order', function () {
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

    expect($orderPage->cart)->toBeEmpty();
    expect($orderPage->showPaymentPopup)->toBeFalse();
    expect(session('cart'))->toBeNull();

    $updatedProduct = Product::find($product->id);
    expect($updatedProduct->stock)->toBe(4);

    $this->assertDatabaseHas('orders', [
        'product_id'  => $product->id,
        'quantity'    => 1,
        'total_price' => 250,
    ]);
});

test('confirmOrder does nothing if the cart is empty', function () {
    $orderPage = new OrderPage();
    $orderPage->cart = [];
    $orderPage->paymentAmount = 0;
    $orderPage->confirmOrder();

    expect($orderPage->cart)->toBeEmpty();
});

test('confirmOrder does not process the order if the payment is insufficient', function () {
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
    expect($updatedProduct->stock)->toBe(5);

    $this->assertDatabaseMissing('orders', [
        'product_id' => $product->id,
    ]);
});
