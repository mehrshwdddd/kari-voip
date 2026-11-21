<?php

namespace App\Models;

use App\Enums\CampaignStatusEnum;
use App\Enums\CampaignTypeEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Campaign extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'name',
        'description',
        'type',
        'audio_path',
        'tts_text',
        'tts_voice',
        'status',
        'contact_list_id',
        'workflow_id',
        'scheduled_start_at',
        'scheduled_end_at',
        'allowed_hours_start',
        'allowed_hours_end',
        'allowed_days',
        'max_concurrent_calls',
        'max_retry_count',
        'retry_interval_minutes',
        'dtmf_options',
        'human_transfer_enabled',
        'record_calls',
        'caller_id',
        'total_contacts',
        'completed_contacts',
        'success_count',
        'failed_count',
        'created_by',
    ];

    protected $appends = [
        'progress_percentage',
    ];

    protected function casts(): array
    {
        return [
            'type' => CampaignTypeEnum::class,
            'status' => CampaignStatusEnum::class,
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'allowed_days' => 'array',
            'max_concurrent_calls' => 'integer',
            'max_retry_count' => 'integer',
            'retry_interval_minutes' => 'array',
            'dtmf_options' => 'array',
            'human_transfer_enabled' => 'boolean',
            'record_calls' => 'boolean',
            'total_contacts' => 'integer',
            'completed_contacts' => 'integer',
            'success_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }

    protected function progressPercentage(): Attribute
    {
        return Attribute::make(
            get: function (): int {
                if ($this->total_contacts <= 0) {
                    return 0;
                }

                return (int) min(100, round(($this->completed_contacts / $this->total_contacts) * 100));
            }
        );
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function contactList(): BelongsTo
    {
        return $this->belongsTo(ContactList::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(CampaignWorkflow::class, 'workflow_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function campaignContacts(): HasMany
    {
        return $this->hasMany(CampaignContact::class);
    }

    public function callLogs(): HasMany
    {
        return $this->hasMany(CampaignCallLog::class)->latest('created_at');
    }
}
