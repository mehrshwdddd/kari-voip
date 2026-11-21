<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'branch_id',
        'contact_id',
        'service_id',
        'staff_id',
        'booking_number',
        'status',
        'starts_at',
        'ends_at',
        'duration_minutes',
        'price',
        'discount',
        'tax',
        'total_amount',
        'paid_amount',
        'payment_status',
        'source',
        'call_id',
        'created_by',
        'reminder_sent_at',
        'notes',
        'cancellation_reason',
        'cancelled_at',
        'recurring_rule',
        'parent_appointment_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'duration_minutes' => 'integer',
            'price' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'recurring_rule' => 'array',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (Appointment $appointment): void {
            if (empty($appointment->uuid)) {
                $appointment->uuid = (string) Str::uuid();
            }
            if (empty($appointment->booking_number)) {
                $appointment->booking_number = 'APT-'.now()->format('ymd').'-'.strtoupper(Str::random(4));
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(AppointmentReminder::class);
    }
}
