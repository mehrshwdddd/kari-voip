<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ContactList extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'source',
        'total_count',
        'valid_count',
        'duplicate_count',
        'status',
        'file_path',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'total_count' => 'integer',
            'valid_count' => 'integer',
            'duplicate_count' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
