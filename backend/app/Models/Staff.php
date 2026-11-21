<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Staff extends Model
{
    use HasFactory;

    protected $table = 'staff';

    protected $fillable = [
        'branch_id',
        'user_id',
        'name',
        'title',
        'avatar',
        'bio',
        'phone',
        'email',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(StaffSchedule::class);
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(StaffBreak::class);
    }

    public function timeOffs(): HasMany
    {
        return $this->hasMany(StaffTimeOff::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
