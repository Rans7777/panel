<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class ProductOption extends Model
{
    use HasFactory;

    protected $table = 'product_options';

    protected $fillable = [
        'option_name', 'price',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
