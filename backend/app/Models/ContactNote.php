<?php

namespace App\Models;

use App\Enums\NoteTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ContactNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'user_id',
        'content',
        'type',
        'is_pinned',
        'due_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => NoteTypeEnum::class,
            'is_pinned' => 'boolean',
            'due_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
