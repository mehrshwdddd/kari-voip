<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class CompanyProfile extends Model
{
    use HasFactory;

    protected $table = 'company_profile';

    protected $fillable = [
        'name',
        'logo',
        'phone',
        'address',
        'national_id',
        'website',
        'email',
        'timezone',
        'working_hours',
        'invoice_prefix',
        'invoice_counter',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'working_hours' => 'array',
            'settings' => 'array',
            'invoice_counter' => 'integer',
        ];
    }
}
