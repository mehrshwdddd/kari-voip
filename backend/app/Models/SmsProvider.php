<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class SmsProvider extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'adapter_class',
        'credentials',
        'sender_number',
        'is_active',
        'priority',
        'created_at',
    ];

    protected $hidden = [
        'credentials',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'is_active' => 'boolean',
            'priority' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
