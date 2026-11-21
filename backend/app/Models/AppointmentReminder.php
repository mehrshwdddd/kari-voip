<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AppointmentReminder extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'appointment_id',
        'channel',
        'type',
        'scheduled_at',
        'sent_at',
        'status',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
