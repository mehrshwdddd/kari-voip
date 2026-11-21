<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ContactMerge extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'primary_contact_id',
        'merged_contact_id',
        'merged_by',
        'merged_at',
    ];

    protected function casts(): array
    {
        return [
            'merged_at' => 'datetime',
        ];
    }

    public function primaryContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'primary_contact_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merged_by');
    }
}
