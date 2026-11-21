<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class InvoiceTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_default',
        'header_text',
        'footer_text',
        'logo_path',
        'color_scheme',
        'show_tax',
        'show_discount',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'show_tax' => 'boolean',
            'show_discount' => 'boolean',
        ];
    }
}
