<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
});

test('admin can register a new user', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $role = Role::first();

    Livewire::test(\App\Filament\Resources\UserResource\Pages\CreateUser::class)
        ->set('data.name', 'New User')
        ->set('data.email', 'newuser@example.com')
        ->set('data.password', 'secret123')
        ->set('data.is_active', true)
        ->set('data.roles', [$role->id])
        ->call('create')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('users', [
        'name'  => 'New User',
        'email' => 'newuser@example.com',
    ]);
});

test('admin can update an existing user', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $role = Role::first();

    $user = User::factory()->create([
        'name'  => 'Old Name',
        'email' => 'old@example.com',
    ]);

    Livewire::test(\App\Filament\Resources\UserResource\Pages\EditUser::class, ['record' => $user->id])
        ->set('data.name', 'Updated Name')
        ->set('data.email', 'updated@example.com')
        ->set('data.password', 'secret123') // Provide a valid password because it's required.
        ->set('data.roles', [$role->id]) // Provide a valid role id.
        ->call('save') // Assumes that the "save" method is used for record saving.
        ->assertHasNoErrors();

    $this->assertDatabaseHas('users', [
        'id'    => $user->id,
        'name'  => 'Updated Name',
        'email' => 'updated@example.com',
    ]);
});

test('admin can toggle user active status', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $user = User::factory()->create([
        'is_active' => true,
    ]);

    $user->update(['is_active' => !$user->is_active]);

    $this->assertFalse((bool) $user->fresh()->is_active);
});

test('admin can delete a user', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $user = User::factory()->create();

    $user->delete();

    $this->assertDatabaseMissing('users', [
        'id' => $user->id,
    ]);
});
