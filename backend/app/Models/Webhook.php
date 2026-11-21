<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'secret',
        'events',
        'is_active',
        'retry_count',
        'last_triggered_at',
        'last_status_code',
    ];

    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
        'retry_count' => 'integer',
        'last_triggered_at' => 'datetime',
        'last_status_code' => 'integer',
    ];

    protected $hidden = [
        'secret',
    ];

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * Determine whether the webhook listens to a specific event.
     */
    public function listensTo(string $event): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if (empty($this->events)) {
            return false;
        }

        return in_array('*', $this->events) || in_array($event, $this->events);
    }
}
