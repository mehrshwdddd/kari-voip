<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StaffTimeOff extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'staff_id',
        'date',
        'start_time',
        'end_time',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
