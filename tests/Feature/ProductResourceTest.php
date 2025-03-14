<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin']);
    }

    public function test_admin_can_create_product()
    {
        $admin = User::factory()->create([
            'name'      => 'adminuser',
            'password'  => bcrypt('password'),
            'is_active' => true,
        ]);
        $admin->assignRole('admin');

        $this->actingAs($admin);

        Livewire::test(\App\Filament\Resources\ProductResource\Pages\CreateProduct::class)
            ->set('data.name', 'Test Product')
            ->set('data.price', 100)
            ->set('data.stock', 10)
            ->set('data.allergens', ['卵', '乳'])
            ->call('create')
            ->assertRedirect('/admin/products');

        $this->assertDatabaseHas('products', [
            'name'  => 'Test Product',
            'price' => 100,
            'stock' => 10,
        ]);
    }

    public function test_admin_can_update_product()
    {
        $admin = User::factory()->create([
            'name'      => 'adminuser',
            'password'  => bcrypt('password'),
            'is_active' => true,
        ]);
        $admin->assignRole('admin');

        $product = Product::factory()->create([
            'name'  => 'Old Product Name',
            'price' => 100,
            'stock' => 10,
        ]);

        $this->actingAs($admin);

        Livewire::test(\App\Filament\Resources\ProductResource\Pages\EditProduct::class, [
                'record' => $product->slug,
            ])
            ->set('data.name', 'Updated Test Product')
            ->set('data.price', 150)
            ->set('data.stock', 20)
            ->call('save')
            ->assertSuccessful();

        $this->assertDatabaseHas('products', [
            'id'    => $product->id,
            'name'  => 'Updated Test Product',
            'price' => 150,
            'stock' => 20,
        ]);
    }

    public function test_admin_can_delete_product()
    {
        Storage::fake('public');

        Storage::disk('public')->put('products/test.jpg', 'dummy content');

        $product = Product::factory()->create([
            'name'  => 'Delete Test Product',
            'price' => 200,
            'stock' => 5,
            'image' => 'products/test.jpg',
        ]);

        $admin = User::factory()->create([
            'name'      => 'adminuser',
            'password'  => bcrypt('password'),
            'is_active' => true,
        ]);
        $admin->assignRole('admin');

        $this->actingAs($admin);

        Livewire::test(\App\Filament\Resources\ProductResource\Pages\ListProducts::class)
            ->callTableAction('delete', $product->getKey());

        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);

        Storage::disk('public')->assertMissing('products/test.jpg');
    }
}
