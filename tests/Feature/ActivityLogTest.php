<?php

namespace Tests\Feature;

use App\Filament\Pages\ActivityLogs;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

test('admin user can mount activity logs page successfully', function () {
    $adminUser = new class implements Authenticatable {
        public function getAuthIdentifierName() { return 'id'; }
        public function getAuthIdentifier() { return 1; }
        public function getAuthPassword() { return ''; }
        public function getAuthPasswordName() { return 'password'; }
        public function getRememberToken() { return null; }
        public function setRememberToken($value) {}
        public function getRememberTokenName() { return 'remember_token'; }
        public function hasRole($role) { return $role === 'admin'; }
    };

    $this->actingAs($adminUser);

    $page = new ActivityLogs();
    $page->mount();

    expect($page)->toBeInstanceOf(ActivityLogs::class);

    $reflection = new \ReflectionClass($page);
    $method = $reflection->getMethod('isTableReorderable');
    $method->setAccessible(true);
    $isReorderable = $method->invoke($page);
    expect($isReorderable)->toBeFalse();
});

test('non-admin user is redirected when mounting the activity logs page', function () {
    $nonAdminUser = new class implements Authenticatable {
        public function getAuthIdentifierName() { return 'id'; }
        public function getAuthIdentifier() { return 2; }
        public function getAuthPassword() { return ''; }
        public function getAuthPasswordName() { return 'password'; }
        public function getRememberToken() { return null; }
        public function setRememberToken($value) {}
        public function getRememberTokenName() { return 'remember_token'; }
        public function hasRole($role) { return false; }
    };

    $this->actingAs($nonAdminUser);
    $page = new ActivityLogs();

    try {
        $page->mount();
        $this->fail('Expected HttpResponseException was not thrown.');
    } catch (HttpResponseException $exception) {
        $response = $exception->getResponse();
        expect($response)->toBeInstanceOf(RedirectResponse::class);
        expect($response->getTargetUrl())->toBe('/admin/');
    }
});

test('getTableQuery returns activities ordered by created_at descending', function () {
    $adminUser = new class implements Authenticatable {
        public function getAuthIdentifierName() { return 'id'; }
        public function getAuthIdentifier() { return 1; }
        public function getAuthPassword() { return ''; }
        public function getAuthPasswordName() { return 'password'; }
        public function getRememberToken() { return null; }
        public function setRememberToken($value) {}
        public function getRememberTokenName() { return 'remember_token'; }
        public function hasRole($role) { return $role === 'admin'; }
    };

    $this->actingAs($adminUser);

    $newActivity = Activity::create([
        'log_name'    => 'info',
        'description' => 'New activity',
        'subject_id'  => 1,
        'subject_type'=> 'Test',
        'causer_id'   => 1,
        'causer_type' => 'Test',
        'properties'  => ['ip_address' => '127.0.0.1'],
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    Activity::create([
        'log_name'    => 'warning',
        'description' => 'Old activity',
        'subject_id'  => 1,
        'subject_type'=> 'Test',
        'causer_id'   => 1,
        'causer_type' => 'Test',
        'properties'  => ['ip_address' => '127.0.0.2'],
        'created_at'  => now()->subDay(),
        'updated_at'  => now()->subDay(),
    ]);

    $page = new ActivityLogs();
    $page->mount();

    $reflection = new \ReflectionClass($page);
    $method = $reflection->getMethod('getTableQuery');
    $method->setAccessible(true);
    $query = $method->invoke($page);
    $activities = $query->get();

    expect($activities->first()->id)->toBe($newActivity->id);
});

test('getTableFilters returns correct filter types', function () {
    $adminUser = new class implements Authenticatable {
        public function getAuthIdentifierName() { return 'id'; }
        public function getAuthIdentifier() { return 1; }
        public function getAuthPassword() { return ''; }
        public function getAuthPasswordName() { return 'password'; }
        public function getRememberToken() { return null; }
        public function setRememberToken($value) {}
        public function getRememberTokenName() { return 'remember_token'; }
        public function hasRole($role) { return $role === 'admin'; }
    };

    $this->actingAs($adminUser);
    $page = new ActivityLogs();
    $page->mount();

    $reflection = new \ReflectionClass($page);
    $method = $reflection->getMethod('getTableFilters');
    $method->setAccessible(true);
    $filters = $method->invoke($page);

    expect(is_array($filters))->toBeTrue();
    expect(count($filters))->toBe(2);
    expect($filters[0])->toBeInstanceOf(\Filament\Tables\Filters\SelectFilter::class);
    expect($filters[1])->toBeInstanceOf(\Filament\Tables\Filters\Filter::class);
});

test('getTableColumns returns an array of columns', function () {
    $adminUser = new class implements Authenticatable {
        public function getAuthIdentifierName() { return 'id'; }
        public function getAuthIdentifier() { return 1; }
        public function getAuthPassword() { return ''; }
        public function getAuthPasswordName() { return 'password'; }
        public function getRememberToken() { return null; }
        public function setRememberToken($value) {}
        public function getRememberTokenName() { return 'remember_token'; }
        public function hasRole($role) { return $role === 'admin'; }
    };

    $this->actingAs($adminUser);
    $page = new ActivityLogs();
    $page->mount();

    $reflection = new \ReflectionClass($page);
    $method = $reflection->getMethod('getTableColumns');
    $method->setAccessible(true);
    $columns = $method->invoke($page);

    expect(is_array($columns))->toBeTrue();
    expect(count($columns))->toBe(4);
});

test('getViewData includes table data', function () {
    $adminUser = new class implements Authenticatable {
        public function getAuthIdentifierName() { return 'id'; }
        public function getAuthIdentifier() { return 1; }
        public function getAuthPassword() { return ''; }
        public function getAuthPasswordName() { return 'password'; }
        public function getRememberToken() { return null; }
        public function setRememberToken($value) {}
        public function getRememberTokenName() { return 'remember_token'; }
        public function hasRole($role) { return $role === 'admin'; }
    };

    $this->actingAs($adminUser);
    $page = new ActivityLogs();
    $page->mount();

    $reflection = new \ReflectionClass($page);
    $tableProperty = $reflection->getProperty('table');
    $tableProperty->setAccessible(true);
    $tableReflection = new \ReflectionClass(\Filament\Tables\Table::class);
    $dummyTable = $tableReflection->newInstanceWithoutConstructor();
    $tableProperty->setValue($page, $dummyTable);

    $method = $reflection->getMethod('getViewData');
    $method->setAccessible(true);
    $viewData = $method->invoke($page);

    expect(array_key_exists('table', $viewData))->toBeTrue();
    expect($viewData['table'])->toBe($dummyTable);
});
