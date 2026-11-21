<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StaffSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_working',
    ];

    protected function casts(): array
    {
        return [
            'is_working' => 'boolean',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
