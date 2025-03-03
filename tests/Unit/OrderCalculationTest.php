<?php

use App\Filament\Pages\OrderPage;

function callPrivateCalculateTotalPrice(OrderPage $orderPage): void
{
    $reflection = new ReflectionClass(OrderPage::class);
    $method = $reflection->getMethod('calculateTotalPrice');
    $method->setAccessible(true);
    $method->invoke($orderPage);
}

test('calculateTotalPrice returns correct total for a non-empty cart', function () {
    $orderPage = new OrderPage();
    $orderPage->cart = [
        ['id' => 1, 'price' => 100, 'quantity' => 2],
        ['id' => 2, 'price' => 300, 'quantity' => 1],
    ];
    callPrivateCalculateTotalPrice($orderPage);
    expect($orderPage->totalPrice)->toEqual(500);
});

test('calculateTotalPrice returns zero for an empty cart', function () {
    $orderPage = new OrderPage();
    $orderPage->cart = [];
    callPrivateCalculateTotalPrice($orderPage);
    expect($orderPage->totalPrice)->toEqual(0);
});

test('calculateTotalPrice calculates correct subtotals for multiple items', function () {
    $orderPage = new OrderPage();
    $orderPage->cart = [
       ['id' => 1, 'price' => 150, 'quantity' => 1],
       ['id' => 2, 'price' => 200, 'quantity' => 3],
       ['id' => 3, 'price' => 50, 'quantity' => 5],
    ];
    callPrivateCalculateTotalPrice($orderPage);
    expect($orderPage->totalPrice)->toEqual(1000);
});

test('calculateTotalPrice calculates correct total for a single item', function () {
    $orderPage = new OrderPage();
    $orderPage->cart = [
        ['id' => 1, 'price' => 50, 'quantity' => 4],
    ];
    callPrivateCalculateTotalPrice($orderPage);
    expect($orderPage->totalPrice)->toEqual(200);
});

test('calculateTotalPrice handles items with options correctly', function () {
    $orderPage = new OrderPage();
    $orderPage->cart = [
        ['id' => 1, 'price' => 300, 'quantity' => 2, 'options' => [
            ['id' => 101, 'option_name' => 'Extra Feature', 'price' => 50]
        ]],
    ];
    callPrivateCalculateTotalPrice($orderPage);
    expect($orderPage->totalPrice)->toEqual(600);
});

test('calculateTotalPrice handles large numbers correctly', function () {
    $orderPage = new OrderPage();
    $orderPage->cart = [
        ['id' => 1, 'price' => 9999999, 'quantity' => 2],
        ['id' => 2, 'price' => 8888888, 'quantity' => 1],
    ];
    callPrivateCalculateTotalPrice($orderPage);
    expect($orderPage->totalPrice)->toEqual(28888886);
});

test('calculateChange returns correct change for sufficient payment', function () {
    $orderPage = new OrderPage();
    $orderPage->totalPrice = 500;
    $orderPage->paymentAmount = 1000;
    $orderPage->calculateChange();
    expect($orderPage->changeAmount)->toEqual(500);
});

test('calculateChange returns negative change for insufficient payment', function () {
    $orderPage = new OrderPage();
    $orderPage->totalPrice = 800;
    $orderPage->paymentAmount = 700;
    $orderPage->calculateChange();
    expect($orderPage->changeAmount)->toEqual(-100);
});

test('calculateChange returns zero change when payment equals total', function () {
    $orderPage = new OrderPage();
    $orderPage->totalPrice = 300;
    $orderPage->paymentAmount = 300;
    $orderPage->calculateChange();
    expect($orderPage->changeAmount)->toEqual(0);
});

test('calculateChange returns negative value when no payment is made', function () {
    $orderPage = new OrderPage();
    $orderPage->totalPrice = 400;
    $orderPage->paymentAmount = 0;
    $orderPage->calculateChange();
    expect($orderPage->changeAmount)->toEqual(-400);
});

test('totalPrice recalculates correctly after modifying cart items', function () {
    $orderPage = new OrderPage();
    $orderPage->cart = [
        ['id' => 1, 'price' => 100, 'quantity' => 1],
        ['id' => 2, 'price' => 200, 'quantity' => 2],
    ];
    callPrivateCalculateTotalPrice($orderPage);
    expect($orderPage->totalPrice)->toEqual(500);
    $orderPage->cart[0]['quantity'] = 3;
    $orderPage->cart[1]['quantity'] = 3;
    callPrivateCalculateTotalPrice($orderPage);
    expect($orderPage->totalPrice)->toEqual(900);
});
