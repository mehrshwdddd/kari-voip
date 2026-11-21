<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CampaignCallLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'campaign_id',
        'campaign_contact_id',
        'contact_id',
        'phone_number',
        'attempt_number',
        'call_id',
        'voip_call_id',
        'started_at',
        'answered_at',
        'ended_at',
        'duration_seconds',
        'listen_duration_seconds',
        'listen_percentage',
        'dtmf_input',
        'outcome',
        'agent_transfer_status',
        'recording_path',
        'retry_count',
        'error_message',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'duration_seconds' => 'integer',
            'listen_duration_seconds' => 'integer',
            'listen_percentage' => 'integer',
            'retry_count' => 'integer',
            'started_at' => 'datetime',
            'answered_at' => 'datetime',
            'ended_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function campaignContact(): BelongsTo
    {
        return $this->belongsTo(CampaignContact::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }
}
