<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MessageLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'channel',
        'to_address',
        'from_address',
        'template_id',
        'subject',
        'body',
        'status',
        'provider',
        'provider_ref',
        'provider_response',
        'sent_at',
        'delivered_at',
        'read_at',
        'scheduled_at',
        'reference_type',
        'reference_id',
    ];

    protected function casts(): array
    {
        return [
            'provider_response' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'scheduled_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'template_id');
    }
}
