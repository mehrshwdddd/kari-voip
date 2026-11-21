<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IvrSession extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'call_id',
        'flow_id',
        'current_node_id',
        'collected_data',
        'dtmf_input',
        'intent_detected',
        'language',
        'started_at',
        'ended_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'collected_data' => 'array',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(IvrFlow::class, 'flow_id');
    }
}
