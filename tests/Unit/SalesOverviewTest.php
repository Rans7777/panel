<?php

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use App\Filament\Widgets\SalesOverview;
use App\Models\User;
use App\Models\Order;
use Spatie\Permission\Models\Role;
uses(TestCase::class, RefreshDatabase::class);

test('SalesOverview widget returns cards correctly', function () {
    $now = Carbon::create(2023, 10, 10, 12, 0, 0);
    Carbon::setTestNow($now);

    Role::firstOrCreate(['name' => 'admin']);

    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);

    Order::factory()->create([
        'total_price' => 2000,
        'created_at'  => $now,
    ]);
    Order::factory()->create([
        'total_price' => 1000,
        'created_at'  => $now->copy()->subDay(),
    ]);
    Order::factory()->create([
        'total_price' => 500,
        'created_at'  => $now->copy()->subDays(2),
    ]);

    expect(SalesOverview::canView())->toBeTrue();

    $widget = new SalesOverview();
    $reflection = new \ReflectionClass(SalesOverview::class);
    $method = $reflection->getMethod('getCards');
    $method->setAccessible(true);
    $cards = $method->invoke($widget);

    expect(count($cards))->toBe(3);

    $todayCard = $cards[0];

    $cardReflection = new \ReflectionObject($todayCard);

    $labelProperty = $cardReflection->getProperty('label');
    $labelProperty->setAccessible(true);
    expect($labelProperty->getValue($todayCard))->toBe('今日の売上');

    $valueProperty = $cardReflection->getProperty('value');
    $valueProperty->setAccessible(true);
    $value = $valueProperty->getValue($todayCard);
    expect($value->toHtml())->toBe('¥'.number_format(2000));

    Carbon::setTestNow();
});
