<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DoNotCall extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'do_not_call_list';

    protected $fillable = [
        'phone_number',
        'reason',
        'source',
        'added_by',
        'added_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'added_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
