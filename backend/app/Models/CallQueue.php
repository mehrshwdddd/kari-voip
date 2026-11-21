<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CallQueue extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'strategy',
        'max_wait_seconds',
        'music_on_hold_path',
        'agents',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'agents' => 'array',
            'max_wait_seconds' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class, 'queue_id');
    }
}
