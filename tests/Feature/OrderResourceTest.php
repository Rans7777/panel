<?php

use App\Filament\Resources\OrderResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'user']);
});

function invokePrivateStaticMethod(string $method, array $arguments = [])
{
    $reflection = new ReflectionClass(OrderResource::class);
    $methodReflection = $reflection->getMethod($method);
    $methodReflection->setAccessible(true);
    return $methodReflection->invokeArgs(null, $arguments);
}

test('Format options for form with list input', function () {
    DB::connection()->getPdo();

    $input = [
        ['option_name' => 'Extra Cheese', 'price' => 1000],
        ['option_name' => 'Extra Sauce', 'price' => 500],
    ];
    $expected = 'Extra Cheese: 1000, Extra Sauce: 500';
    $result = invokePrivateStaticMethod('formatOptionsForForm', [$input]);
    expect($result)->toBe($expected);
});

test('Format options for form with associative input', function () {
    DB::connection()->getPdo();

    $input = [
        'option1' => ['price' => 1000],
        'option2' => ['price' => 2000],
    ];
    $expected = 'option1: 1000, option2: 2000';
    $result = invokePrivateStaticMethod('formatOptionsForForm', [$input]);
    expect($result)->toBe($expected);
});

test('Format options for form with JSON string input', function () {
    DB::connection()->getPdo();

    $inputArray = [
        ['option_name' => 'Extra Cheese', 'price' => 1000],
        ['option_name' => 'Extra Sauce', 'price' => 500],
    ];
    $input = json_encode($inputArray);
    $expected = 'Extra Cheese: 1000, Extra Sauce: 500';
    $result = invokePrivateStaticMethod('formatOptionsForForm', [$input]);
    expect($result)->toBe($expected);
});

test('Format options for form with invalid JSON string returns original string', function () {
    DB::connection()->getPdo();

    $input = 'invalid json';
    $result = invokePrivateStaticMethod('formatOptionsForForm', [$input]);
    expect($result)->toBe($input);
});

test('Format options for table with list input', function () {
    DB::connection()->getPdo();

    $input = [
        ['option_name' => 'Extra Cheese that is so long it must be shortened', 'price' => 1000],
        ['option_name' => 'Extra Sauce', 'price' => 500],
    ];
    $limit = 20;
    $firstOptionFull = 'Extra Cheese that is so long it must be shortened';
    $firstOptionShort = (mb_strlen($firstOptionFull) > $limit)
        ? mb_substr($firstOptionFull, 0, $limit) . '…'
        : $firstOptionFull;
    $expected = $firstOptionShort . ', ' . 'Extra Sauce';
    $result = invokePrivateStaticMethod('formatOptionsForTable', [$input]);
    expect($result)->toBe($expected);
});

test('Format options for table with associative input', function () {
    DB::connection()->getPdo();

    $input = [
        'LongOptionNameThatExceedsLimit' => ['price' => 1000],
        'ShortOption' => ['price' => 500],
    ];
    $limit = 20;
    $firstKey = 'LongOptionNameThatExceedsLimit';
    $firstKeyShort = (mb_strlen($firstKey) > $limit)
        ? mb_substr($firstKey, 0, $limit) . '…'
        : $firstKey;
    $expected = $firstKeyShort . ', ' . 'ShortOption';
    $result = invokePrivateStaticMethod('formatOptionsForTable', [$input]);
    expect($result)->toBe($expected);
});

test('Shorten text method works correctly', function () {
    DB::connection()->getPdo();

    $text = 'This is a very long text that needs to be shortened';
    $limit = 10;
    $shortened = invokePrivateStaticMethod('shortenText', [$text, $limit]);
    expect(mb_strlen($shortened))->toBeLessThanOrEqual($limit + mb_strlen('…'));
    expect(mb_substr($shortened, -1))->toBe('…');
});

test('Navigation registration returns true for admin user', function () {
    DB::connection()->getPdo();

    $adminUser = User::factory()->create();
    $adminUser->assignRole('admin');
    auth()->setUser($adminUser);
    $result = OrderResource::shouldRegisterNavigation();
    expect($result)->toBeTrue();
});

test('Navigation registration returns false for guest user', function () {
    DB::connection()->getPdo();

    auth()->logout();
    $result = OrderResource::shouldRegisterNavigation();
    expect($result)->toBeFalse();
});

test('Navigation registration returns false for non-admin user', function () {
    DB::connection()->getPdo();

    $nonAdminUser = User::factory()->create();
    $nonAdminUser->assignRole('user');
    auth()->setUser($nonAdminUser);
    $result = OrderResource::shouldRegisterNavigation();
    expect($result)->toBeFalse();
});
