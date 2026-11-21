<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class CallDisposition extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'color',
        'requires_note',
    ];

    protected function casts(): array
    {
        return [
            'requires_note' => 'boolean',
        ];
    }
}
