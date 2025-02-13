<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class LoginAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'ip_address',
        'attempts',
        'last_attempt_at',
    ];
}
