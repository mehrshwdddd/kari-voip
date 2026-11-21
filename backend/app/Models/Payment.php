<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

final class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'invoice_id',
        'contact_id',
        'amount',
        'method',
        'gateway_name',
        'gateway_ref',
        'gateway_response',
        'status',
        'paid_at',
        'notes',
        'processed_by',
    ];

    protected $hidden = [
        'gateway_response',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'gateway_response' => 'encrypted:array',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (Payment $payment): void {
            if (empty($payment->uuid)) {
                $payment->uuid = (string) Str::uuid();
            }
            if (empty($payment->paid_at)) {
                $payment->paid_at = now();
            }
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function cheque(): HasOne
    {
        return $this->hasOne(Cheque::class);
    }
}
