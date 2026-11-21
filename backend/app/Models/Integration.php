<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Integration extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'adapter_class',
        'credentials',
        'config',
        'status',
        'last_tested_at',
        'last_error',
    ];

    protected $hidden = [
        'credentials',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'config' => 'array',
            'last_tested_at' => 'datetime',
        ];
    }
}
