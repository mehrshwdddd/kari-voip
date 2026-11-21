<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StaffBreak extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'staff_id',
        'day_of_week',
        'start_time',
        'end_time',
        'label',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
