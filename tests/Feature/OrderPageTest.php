<?php

use App\Filament\Pages\OrderPage;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('mount initializes cart and total price', function () {
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
    expect($orderPage->totalPrice)->toEqual(200);
});

test('addToCart success', function () {
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
    expect($orderPage->cart[0]['id'])->toEqual($product->id);
    expect($orderPage->cart[0]['quantity'])->toEqual(1);
    expect($orderPage->totalPrice)->toEqual(150);
    expect(session('cart'))->toEqual($orderPage->cart);
});

test('addToCart increments quantity if item exists', function () {
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
    expect($orderPage->cart[0]['quantity'])->toEqual(2);
    expect($orderPage->totalPrice)->toEqual(300);
});

test('addToCart does not add when stock insufficient', function () {
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
    expect($orderPage->cart[0]['quantity'])->toEqual(1);
});

test('updateQuantity updates quantity correctly', function () {
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

    expect($orderPage->cart[0]['quantity'])->toEqual(3);
    expect($orderPage->totalPrice)->toEqual(600);
});

test('updateQuantity removes item when quantity set to zero', function () {
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

    expect($orderPage->cart)->toHaveCount(0);
    expect($orderPage->totalPrice)->toEqual(0);
});

test('removeFromCart removes item correctly', function () {
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
    expect($orderPage->cart)->toHaveCount(0);
});

test('calculateChange calculates correctly', function () {
    $orderPage = new OrderPage();
    $orderPage->totalPrice = 500;
    $orderPage->paymentAmount = 800;
    $orderPage->calculateChange();

    expect($orderPage->changeAmount)->toEqual(300);
});

test('updatedPaymentAmount updates correctly', function () {
    $orderPage = new OrderPage();
    $orderPage->updatedPaymentAmount(1000);
    expect($orderPage->paymentAmount)->toEqual(1000);
    $orderPage->updatedPaymentAmount('');
    expect($orderPage->paymentAmount)->toEqual(0);
});

test('confirmOrder successful order', function () {
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
    expect($updatedProduct->stock)->toEqual(4);

    $this->assertDatabaseHas('orders', [
        'product_id'  => $product->id,
        'quantity'    => 1,
        'total_price' => 250,
    ]);
});

test('confirmOrder fails with empty cart', function () {
    $orderPage = new OrderPage();
    $orderPage->cart = [];
    $orderPage->paymentAmount = 0;
    $orderPage->confirmOrder();

    expect($orderPage->cart)->toBeEmpty();
});

test('confirmOrder fails with insufficient payment', function () {
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
    expect($updatedProduct->stock)->toEqual(5);

    $this->assertDatabaseMissing('orders', [
        'product_id' => $product->id,
    ]);
});

test('handleProductClick shows options popup when options exist', function () {
    $product = Product::create([
        'name'  => 'Test Product with Options',
        'image' => 'test.jpg',
        'price' => 200,
        'stock' => 10,
    ]);

    $product->options()->create([
        'option_name' => 'Extra Cheese',
        'price'       => 50,
    ]);

    $orderPage = new OrderPage();
    $orderPage->mount();
    $orderPage->handleProductClick($product->id);

    expect($orderPage->showOptionsPopup)->toBeTrue();
    expect($orderPage->selectedProductId)->toEqual($product->id);
    expect($orderPage->selectedProductOptions)->toHaveCount(1);
});

test('confirmOptionSelection adds product with options', function () {
    $product = Product::create([
        'name'  => 'Test Product with Options',
        'image' => 'test.jpg',
        'price' => 200,
        'stock' => 10,
    ]);

    $option = $product->options()->create([
        'option_name' => 'Extra Cheese',
        'price'       => 50,
    ]);

    $orderPage = new OrderPage();
    $orderPage->mount();
    $orderPage->handleProductClick($product->id);
    $orderPage->selectedOptionIds = [$option->id];
    $orderPage->confirmOptionSelection();

    expect($orderPage->cart)->toHaveCount(1);
    expect($orderPage->cart[0]['id'])->toEqual($product->id);
    expect($orderPage->cart[0]['quantity'])->toEqual(1);
    expect($orderPage->cart[0]['price'])->toEqual(250);
    expect($orderPage->cart[0])->toHaveKey('options');
    expect(session('cart'))->toEqual($orderPage->cart);
});

test('cancelOptionSelection resets selection', function () {
    $orderPage = new OrderPage();
    $orderPage->selectedProductId = 1;
    $orderPage->selectedProductOptions = [
        ['id' => 1, 'option_name' => 'Extra Cheese', 'price' => 50]
    ];
    $orderPage->selectedOptionIds = [1];
    $orderPage->showOptionsPopup = true;
    $orderPage->cancelOptionSelection();

    expect($orderPage->selectedProductId)->toBeNull();
    expect($orderPage->selectedProductOptions)->toBeEmpty();
    expect($orderPage->selectedOptionIds)->toBeEmpty();
    expect($orderPage->showOptionsPopup)->toBeFalse();
});
