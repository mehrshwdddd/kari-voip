<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ContactFile extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'contact_id',
        'user_id',
        'name',
        'path',
        'mime_type',
        'size',
        'created_at',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
