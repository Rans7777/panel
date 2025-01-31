<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Orders extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'quantity', 'image', 'total_price'];

    function product()
    {
        return $this->belongsTo(Product::class);
    }
}
