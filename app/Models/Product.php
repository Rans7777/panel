<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
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
