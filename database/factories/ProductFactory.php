<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

final class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition()
    {
        return [
            'stock' => $this->faker->numberBetween(1, 100),
            'price' => $this->faker->numberBetween(500, 2000),
            'name' => $this->faker->word,
            'image' => $this->faker->imageUrl,
        ];
    }
}
