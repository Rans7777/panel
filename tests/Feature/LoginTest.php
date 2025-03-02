<?php

namespace Tests\Feature;

use App\Models\LoginAttempt;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Auth\Login as FilamentLogin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.turnstile.secret' => null]);
        config(['services.turnstile.sitekey' => null]);
    }

    public function test_user_can_view_login_page()
    {
        $response = $this->get('/admin/login');
        $response->assertStatus(200);
    }

    public function test_user_can_login_with_correct_credentials()
    {
        $user = User::factory()->create([
            'name' => 'testuser',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        Livewire::test(\App\Filament\Pages\Auth\Login::class)
            ->set('name', 'testuser')
            ->set('password', 'password')
            ->call('authenticate');
        $this->assertTrue(Auth::check());
        $this->assertEquals($user->id, Auth::id());
    }

    public function test_login_redirects_to_dashboard()
    {
        $user = User::factory()->create([
            'name' => 'testuser',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        Livewire::test(\App\Filament\Pages\Auth\Login::class)
            ->set('name', 'testuser')
            ->set('password', 'password')
            ->call('authenticate')
            ->assertRedirect('/admin');
        $this->assertTrue(Auth::check());
        $this->assertEquals($user->id, Auth::id());
    }

    public function test_user_cannot_login_with_incorrect_password()
    {
        User::factory()->create([
            'name' => 'testuser',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        Livewire::test(\App\Filament\Pages\Auth\Login::class)
            ->set('name', 'testuser')
            ->set('password', 'wrong-password')
            ->call('authenticate');
        $this->assertFalse(Auth::check());
    }

    public function test_inactive_user_cannot_login()
    {
        User::factory()->create([
            'name' => 'inactive',
            'password' => bcrypt('password'),
            'is_active' => false,
        ]);
        Livewire::test(\App\Filament\Pages\Auth\Login::class)
            ->set('name', 'inactive')
            ->set('password', 'password')
            ->call('authenticate');
        $this->assertFalse(Auth::check());
    }

    public function test_login_attempts_are_tracked()
    {
        User::factory()->create([
            'name' => 'testuser',
            'password' => bcrypt('password'),
        ]);
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);
        Livewire::test(\App\Filament\Pages\Auth\Login::class)
            ->set('name', 'testuser')
            ->set('password', 'wrong-password')
            ->call('authenticate');
        $this->assertDatabaseHas('login_attempts', [
            'ip_address' => '127.0.0.1',
            'attempts' => 1,
        ]);
    }

    public function test_user_is_blocked_after_too_many_attempts()
    {
        config(['auth.attempt_limit' => 3]);
        User::factory()->create([
            'name' => 'testuser',
            'password' => bcrypt('password'),
        ]);
        $ipAddress = '127.0.0.1';
        LoginAttempt::create([
            'ip_address' => $ipAddress,
            'attempts' => 3,
            'last_attempt_at' => now(),
        ]);
        $this->withServerVariables(['REMOTE_ADDR' => $ipAddress]);
        Livewire::test(\App\Filament\Pages\Auth\Login::class)
            ->set('name', 'testuser')
            ->set('password', 'password')
            ->call('authenticate');
        $this->assertFalse(Auth::check());
    }
}
