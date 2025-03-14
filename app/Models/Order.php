<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Order extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'quantity', 'image', 'total_price', 'options', 'uuid'];

    protected $casts = [
        'options' => 'array',
        'uuid' => 'string',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
