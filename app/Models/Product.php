<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'stock',
        'image',
    ];

    // Order モデルとのリレーション
    function orders()
    {
        return $this->hasMany(Orders::class);
    }

    function reduceStock(int $quantity): void
    {
        if ($this->stock < $quantity) {
            throw new \Exception('在庫が不足しています。');
        }

        $this->decrement('stock', $quantity);
    }
}
