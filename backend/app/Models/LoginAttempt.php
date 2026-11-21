<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class LoginAttempt extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'identifier',
        'ip_address',
        'successful',
        'created_at',
    ];

    protected $casts = [
        'successful' => 'boolean',
        'created_at' => 'datetime',
    ];
}
