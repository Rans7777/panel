<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'stock',
        'image',
    ];

    public function orders()
    {
        return $this->hasMany(Orders::class);
    }

    public function options()
    {
        return $this->hasMany(ProductOption::class);
    }
}
