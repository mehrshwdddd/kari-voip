<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Holiday extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'date',
        'is_recurring',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_recurring' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
