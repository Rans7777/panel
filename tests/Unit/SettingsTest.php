<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Filament\Pages\Settings;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class DummyAdmin extends \Illuminate\Database\Eloquent\Model implements Authenticatable
{
    protected $table = 'admin';
    public $incrementing = false;
    public $timestamps = false;
    protected $attributes = ['id' => 1];

    public function getAuthIdentifierName() { return 'id'; }
    public function getAuthIdentifier() { return $this->attributes['id']; }
    public function getAuthPassword() { return ''; }
    public function getRememberToken() { return null; }
    public function setRememberToken($value) { }
    public function getRememberTokenName() { return 'remember_token'; }
    public function getAuthPasswordName() { return 'password'; }
    public function hasRole($role) { return $role === 'admin'; }
}

class DummyNonAdmin extends \Illuminate\Database\Eloquent\Model implements Authenticatable
{
    protected $table = 'user';
    public $incrementing = false;
    public $timestamps = false;
    protected $attributes = ['id' => 2];

    public function getAuthIdentifierName() { return 'id'; }
    public function getAuthIdentifier() { return $this->attributes['id']; }
    public function getAuthPassword() { return ''; }
    public function getRememberToken() { return null; }
    public function setRememberToken($value) { }
    public function getRememberTokenName() { return 'remember_token'; }
    public function getAuthPasswordName() { return 'password'; }
    public function hasRole($role) { return false; }
}

uses(TestCase::class)->in(__DIR__);

test('admin user can mount settings page successfully', function () {
    $adminUser = new DummyAdmin();
    auth()->setUser($adminUser);

    config([
        'app.name'     => 'TestApp',
        'app.debug'    => true,
        'app.timezone' => 'UTC',
        'app.locale'   => 'en',
        'app.url'      => 'https://example.com',
        'logging.level'=> 'debug',
        'database.default' => 'mysql',
        'database.connections.mysql.host' => '127.0.0.1',
        'database.connections.mysql.port' => 3306,
        'database.connections.mysql.database' => 'test_db',
        'database.connections.mysql.username' => 'user',
        'database.connections.mysql.password' => 'secret',
        'services.turnstile.sitekey' => '',
        'services.turnstile.secret' => '',
        'auth.attempt_limit' => 5,
        'auth.block_time'    => 15,
        'discord-alerts.webhook_urls.default' => 'https://example.net',
    ]);

    $dummyForm = new class {
        public $state = [];
        public function fill(array $data) {
            $this->state = $data;
        }
        public function getState() {
            return $this->state;
        }
    };

    $page = new Settings();
    $page->form = $dummyForm;
    $page->mount();

    $formState = $page->form->getState();
    expect($formState['APP_NAME'])->toBe('TestApp');
    expect($formState['APP_DEBUG'])->toBe(true);
    expect($formState['APP_TIMEZONE'])->toBe('UTC');
    expect($formState['APP_LOCALE'])->toBe('en');
    expect($formState['APP_URL'])->toBe('https://example.com');
});

test('non-admin user is redirected when mounting settings page', function () {
    $nonAdminUser = new DummyNonAdmin();
    auth()->setUser($nonAdminUser);

    $page = new Settings();
    $page->form = new class {
        public $state = [];
        public function fill(array $data) {
            $this->state = $data;
        }
        public function getState() {
            return $this->state;
        }
    };

    try {
        $page->mount();
        $this->fail('Expected HttpResponseException was not thrown.');
    } catch (HttpResponseException $ex) {
        $response = $ex->getResponse();
        expect($response)->toBeInstanceOf(RedirectResponse::class);
        expect($response->getTargetUrl())->toBe('/admin/');
    }
});

test('shouldRegisterNavigation returns proper value', function () {
    $adminUser = new DummyAdmin();
    auth()->setUser($adminUser);
    $result = Settings::shouldRegisterNavigation();
    expect($result)->toBeTrue();

    $nonAdminUser = new DummyNonAdmin();
    auth()->setUser($nonAdminUser);
    $result2 = Settings::shouldRegisterNavigation();
    expect($result2)->toBeFalse();
});

test('updateEnv does nothing if .env file does not exist', function () {
    $adminUser = new DummyAdmin();
    auth()->setUser($adminUser);

    $page = new Settings();
    $dummyForm = new class {
        public $state = ['APP_NAME' => 'TestApp'];
        public function getState() {
            return $this->state;
        }
        public function fill(array $data) {
            $this->state = $data;
        }
    };
    $page->form = $dummyForm;

    File::shouldReceive('exists')->once()->andReturn(false);

    $page->updateEnv();

    expect(true)->toBeTrue();
});

test('updateEnv updates .env file successfully', function () {
    $adminUser = new DummyAdmin();
    auth()->setUser($adminUser);

    $page = new Settings();
    $dummyForm = new class {
        public $state = [
            'APP_NAME' => 'TestApp',
            'APP_DEBUG' => false,
            'APP_TIMEZONE' => 'Asia/Tokyo',
            'APP_LOCALE' => 'ja',
            'APP_URL' => 'https://example.org',
            'LOGGING_LEVEL' => 'debug',
            'DATABASE_DEFAULT' => 'sqlite',
        ];
        public function getState() {
            return $this->state;
        }
        public function fill(array $data) {
            $this->state = $data;
        }
    };
    $page->form = $dummyForm;

    $envContent = "APP_NAME=OldApp\nOTHER_KEY=value";
    File::shouldReceive('exists')->once()->andReturn(true);
    File::shouldReceive('get')->once()->andReturn($envContent);

    File::shouldReceive('put')
        ->once()
        ->withArgs(function ($path, $newContent) {
            return strpos($newContent, 'APP_NAME=TestApp') !== false;
        });
    Artisan::shouldReceive('call')->once()->with('config:clear');

    $page->updateEnv();

    expect(true)->toBeTrue();
});
