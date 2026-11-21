<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class ContactTag extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'color',
        'created_at',
    ];

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_tag_pivot', 'tag_id', 'contact_id');
    }
}
