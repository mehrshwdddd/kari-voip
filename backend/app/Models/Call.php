<?php

namespace App\Models;

use App\Enums\CallDirectionEnum;
use App\Enums\CallStatusEnum;
use App\Enums\CallTypeEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

final class Call extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'branch_id',
        'contact_id',
        'caller_number',
        'callee_number',
        'direction',
        'type',
        'status',
        'is_muted',
        'is_recording',
        'user_id',
        'queue_id',
        'voip_provider',
        'voip_call_id',
        'voip_channel',
        'started_at',
        'answered_at',
        'ended_at',
        'duration_seconds',
        'ring_duration',
        'recording_path',
        'disposition',
        'disposition_note',
        'campaign_id',
        'ivr_session_id',
        'transfer_to',
        'transferred_at',
        'metadata',
    ];

    protected $hidden = [
        'recording_path',
    ];

    protected $appends = [
        'has_recording',
    ];

    protected function casts(): array
    {
        return [
            'direction' => CallDirectionEnum::class,
            'type' => CallTypeEnum::class,
            'status' => CallStatusEnum::class,
            'is_muted' => 'boolean',
            'is_recording' => 'boolean',
            'started_at' => 'datetime',
            'answered_at' => 'datetime',
            'ended_at' => 'datetime',
            'transferred_at' => 'datetime',
            'duration_seconds' => 'integer',
            'ring_duration' => 'integer',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (Call $call): void {
            if (empty($call->uuid)) {
                $call->uuid = (string) Str::uuid();
            }
            if (empty($call->started_at)) {
                $call->started_at = now();
            }
        });
    }

    protected function hasRecording(): Attribute
    {
        return Attribute::make(
            get: fn () => ! empty($this->recording_path),
        );
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function queue(): BelongsTo
    {
        return $this->belongsTo(CallQueue::class, 'queue_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CallEvent::class)->orderBy('occurred_at');
    }

    public function ivrSession(): HasOne
    {
        return $this->hasOne(IvrSession::class);
    }
}
