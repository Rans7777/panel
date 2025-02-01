<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
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
}
