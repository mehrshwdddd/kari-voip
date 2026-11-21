<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class IvrFlow extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'nodes',
        'edges',
        'is_active',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'nodes' => 'array',
            'edges' => 'array',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
