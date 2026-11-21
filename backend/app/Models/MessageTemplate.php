<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class MessageTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'channel',
        'category',
        'subject',
        'body',
        'variables',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
