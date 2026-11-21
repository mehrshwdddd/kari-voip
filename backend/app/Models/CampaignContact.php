<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CampaignContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'contact_id',
        'phone_number',
        'status',
        'attempt_count',
        'last_attempt_at',
        'next_attempt_at',
        'final_disposition',
        'opted_out_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'last_attempt_at' => 'datetime',
            'next_attempt_at' => 'datetime',
            'opted_out_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CampaignCallLog::class, 'campaign_contact_id');
    }
}
